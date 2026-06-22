<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\JWTService;
use App\Config\Database;
use App\Config\Redis;
use App\Helpers\Response;

class AuthMiddleware
{
    private JWTService $jwt;

    public function __construct()
    {
        $this->jwt = new JWTService();
    }

    public function handle(): array
    {
        $token = $this->jwt->extractTokenFromHeader();

        if (!$token) {
            Response::unauthorized('Authentication token missing');
            exit;
        }

        // Check if token is blacklisted (logged out)
        if ($this->isTokenBlacklisted($token)) {
            Response::unauthorized('Token has been revoked');
            exit;
        }

        try {
            $payload = $this->jwt->verifyAccessToken($token);
        } catch (\RuntimeException $e) {
            Response::unauthorized($e->getMessage());
            exit;
        }

        $user = $this->loadUser($payload['sub']);
        if (!$user || !$user['is_active']) {
            Response::unauthorized('User not found or inactive');
            exit;
        }

        if ($user['must_change_password']) {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($path !== '/api/auth/change-password') {
                Response::error('Password change required', 403, ['must_change_password' => true]);
                exit;
            }
        }

        return $user;
    }

    public function requirePermission(array $user, string $permission): void
    {
        if (!$this->userHasPermission($user['role_id'], $permission)) {
            Response::forbidden("Permission required: $permission");
            exit;
        }
    }

    public function requireRole(array $user, string|array $roles): void
    {
        $roles = (array) $roles;
        if (!in_array($user['role_name'], $roles, true)) {
            Response::forbidden('Insufficient role for this action');
            exit;
        }
    }

    private function loadUser(string $userId): ?array
    {
        $redis    = Redis::connection();
        $cacheKey = 'user:' . $userId;
        $cached   = $redis->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND u.is_active = TRUE'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user) {
            unset($user['password_hash'], $user['mfa_secret'], $user['mfa_backup_codes']);
            $redis->setex($cacheKey, 300, $user); // cache 5 minutes
        }

        return $user ?: null;
    }

    private function userHasPermission(string $roleId, string $permission): bool
    {
        $redis    = Redis::connection();
        $cacheKey = 'permissions:' . $roleId;
        $perms    = $redis->get($cacheKey);

        if (!$perms) {
            $stmt = Database::connection()->prepare(
                'SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = :rid'
            );
            $stmt->execute(['rid' => $roleId]);
            $perms = array_column($stmt->fetchAll(), 'name');
            $redis->setex($cacheKey, 600, $perms); // cache 10 minutes
        }

        return in_array($permission, $perms, true);
    }

    private function isTokenBlacklisted(string $token): bool
    {
        $redis = Redis::connection();
        return (bool) $redis->get('blacklist:token:' . hash('sha256', $token));
    }
}
