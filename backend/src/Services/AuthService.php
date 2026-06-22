<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\Redis;
use App\Services\AuditService;
use PDO;

class AuthService
{
    public function __construct(
        private readonly JWTService   $jwt = new JWTService(),
        private readonly AuditService $audit = new AuditService()
    ) {}

    public function login(string $email, string $password, array $deviceInfo = []): array
    {
        $db   = Database::connection();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Check rate limit (5 attempts per 30 minutes per IP+email)
        $this->enforceLoginRateLimit($email, $ip);

        $user = $this->findActiveUser($email);

        if (!$user || !$this->verifyPassword($password, $user['password_hash'])) {
            $this->recordFailedAttempt($email, $ip);
            $this->audit->log(null, 'auth.login_failed', 'user', $email, null, null, $ip);
            throw new \RuntimeException('Invalid credentials', 401);
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new \RuntimeException('Account temporarily locked. Please try again later.', 423);
        }

        // Reset failed attempts on success
        $this->resetFailedAttempts($user['id']);

        $tokenPayload = [
            'sub'         => $user['id'],
            'email'       => $user['email'],
            'role'        => $user['role_name'],
            'hospital_id' => $user['hospital_id'],
            'region_id'   => $user['region_id'],
        ];

        $accessToken  = $this->jwt->generateAccessToken($tokenPayload);
        $refreshToken = $this->jwt->generateRefreshToken(['sub' => $user['id']]);

        $this->storeRefreshToken($user['id'], $refreshToken, $deviceInfo, $ip);
        $this->updateLastLogin($user['id'], $ip);
        $this->audit->log($user['id'], 'auth.login', 'user', $user['id'], null, null, $ip);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900),
            'user'          => $this->sanitizeUser($user),
            'mfa_required'  => $user['mfa_enabled'] && !$this->isDeviceTrusted($user['id'], $deviceInfo),
        ];
    }

    public function refreshTokens(string $refreshToken): array
    {
        $payload = $this->jwt->verifyRefreshToken($refreshToken);
        $userId  = $payload['sub'];
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!$this->validateStoredRefreshToken($userId, $refreshToken)) {
            $this->audit->log($userId, 'auth.refresh_invalid', 'user', $userId, null, null, $ip);
            throw new \RuntimeException('Invalid or expired refresh token', 401);
        }

        $user = $this->findUserById($userId);
        if (!$user || !$user['is_active']) {
            throw new \RuntimeException('User not found or inactive', 401);
        }

        // Rotate: revoke old, issue new
        $this->revokeRefreshToken($userId, $refreshToken);

        $newAccessToken  = $this->jwt->generateAccessToken([
            'sub'         => $user['id'],
            'email'       => $user['email'],
            'role'        => $user['role_name'],
            'hospital_id' => $user['hospital_id'],
            'region_id'   => $user['region_id'],
        ]);
        $newRefreshToken = $this->jwt->generateRefreshToken(['sub' => $user['id']]);

        $this->storeRefreshToken($userId, $newRefreshToken, [], $ip);

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900),
        ];
    }

    public function logout(string $userId, string $refreshToken): void
    {
        $this->revokeRefreshToken($userId, $refreshToken);
        $this->audit->log($userId, 'auth.logout', 'user', $userId);
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->findUserById($userId);
        if (!$user || !$this->verifyPassword($currentPassword, $user['password_hash'])) {
            throw new \RuntimeException('Current password is incorrect', 400);
        }

        $this->validatePasswordStrength($newPassword);
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);

        Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, password_changed_at = NOW(), must_change_password = FALSE, updated_at = NOW() WHERE id = :id'
        )->execute(['hash' => $hash, 'id' => $userId]);

        // Revoke all refresh tokens (force re-login on all devices)
        Database::connection()->prepare(
            'UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL'
        )->execute(['id' => $userId]);

        $this->audit->log($userId, 'auth.password_changed', 'user', $userId);
    }

    public function requestPasswordReset(string $email): void
    {
        $user = $this->findActiveUser($email);
        if (!$user) return; // Silent — don't reveal if email exists

        $token    = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $token);
        $expires  = date('Y-m-d H:i:s', time() + 3600);

        Database::connection()->prepare(
            'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (:email, :hash, :expires)'
        )->execute(['email' => $email, 'hash' => $hash, 'expires' => $expires]);

        // Queue email notification
        $mailService = new MailService();
        $mailService->sendPasswordReset($email, $user['name'], $token);

        $this->audit->log($user['id'], 'auth.password_reset_requested', 'user', $user['id']);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $hash = hash('sha256', $token);
        $db   = Database::connection();

        $reset = $db->prepare(
            'SELECT * FROM password_resets WHERE token_hash = :hash AND expires_at > NOW() AND used_at IS NULL'
        );
        $reset->execute(['hash' => $hash]);
        $resetRecord = $reset->fetch();

        if (!$resetRecord) {
            throw new \RuntimeException('Invalid or expired reset token', 400);
        }

        $this->validatePasswordStrength($newPassword);
        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')
               ->execute(['id' => $resetRecord['id']]);

            $user = $this->findActiveUser($resetRecord['email']);
            if ($user) {
                $db->prepare(
                    'UPDATE users SET password_hash = :hash, password_changed_at = NOW(), must_change_password = FALSE, failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id'
                )->execute(['hash' => $passwordHash, 'id' => $user['id']]);

                $db->prepare('UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL')
                   ->execute(['id' => $user['id']]);

                $this->audit->log($user['id'], 'auth.password_reset', 'user', $user['id']);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function findActiveUser(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email AND u.is_active = TRUE'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function findUserById(string $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    private function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 10) {
            throw new \RuntimeException('Password must be at least 10 characters.', 422);
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new \RuntimeException('Password must contain at least one uppercase letter.', 422);
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new \RuntimeException('Password must contain at least one lowercase letter.', 422);
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new \RuntimeException('Password must contain at least one digit.', 422);
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \RuntimeException('Password must contain at least one special character.', 422);
        }
    }

    private function storeRefreshToken(string $userId, string $token, array $deviceInfo, string $ip): void
    {
        $hash    = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + (int) ($_ENV['JWT_REFRESH_TTL'] ?? 2592000));

        Database::connection()->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, device_info, ip_address, expires_at) VALUES (:uid, :hash, :device, :ip, :exp)'
        )->execute([
            'uid'    => $userId,
            'hash'   => $hash,
            'device' => json_encode($deviceInfo),
            'ip'     => $ip,
            'exp'    => $expires,
        ]);
    }

    private function validateStoredRefreshToken(string $userId, string $token): bool
    {
        $hash = hash('sha256', $token);
        $stmt = Database::connection()->prepare(
            'SELECT id FROM refresh_tokens WHERE user_id = :uid AND token_hash = :hash AND expires_at > NOW() AND revoked_at IS NULL'
        );
        $stmt->execute(['uid' => $userId, 'hash' => $hash]);
        return (bool) $stmt->fetch();
    }

    private function revokeRefreshToken(string $userId, string $token): void
    {
        $hash = hash('sha256', $token);
        Database::connection()->prepare(
            'UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = :uid AND token_hash = :hash'
        )->execute(['uid' => $userId, 'hash' => $hash]);
    }

    private function updateLastLogin(string $userId, string $ip): void
    {
        Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = :ip, updated_at = NOW() WHERE id = :id'
        )->execute(['ip' => $ip, 'id' => $userId]);
    }

    private function enforceLoginRateLimit(string $email, string $ip): void
    {
        $redis = Redis::connection();
        $key   = 'rate_limit:login:' . hash('sha256', $ip . $email);
        $count = $redis->get($key) ?: 0;

        if ((int) $count >= (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5)) {
            throw new \RuntimeException('Too many login attempts. Please try again later.', 429);
        }
    }

    private function recordFailedAttempt(string $email, string $ip): void
    {
        $redis  = Redis::connection();
        $key    = 'rate_limit:login:' . hash('sha256', $ip . $email);
        $window = (int) ($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 30) * 60;
        $redis->incr($key);
        $redis->expire($key, $window);

        // Also update DB counter for the user
        Database::connection()->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1,
             locked_until = CASE WHEN failed_login_attempts + 1 >= :max THEN NOW() + INTERVAL 30 MINUTE ELSE NULL END,
             updated_at = NOW() WHERE email = :email'
        )->execute(['max' => (int) ($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5), 'email' => $email]);
    }

    private function resetFailedAttempts(string $userId): void
    {
        Database::connection()->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    private function isDeviceTrusted(string $userId, array $deviceInfo): bool
    {
        // Simplified: trust if device_id has authenticated before successfully
        if (empty($deviceInfo['device_id'])) return false;
        $redis = Redis::connection();
        return (bool) $redis->get('trusted_device:' . $userId . ':' . hash('sha256', $deviceInfo['device_id'] ?? ''));
    }

    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash'], $user['mfa_secret'], $user['mfa_backup_codes']);
        return $user;
    }
}
