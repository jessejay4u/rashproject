<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\MailService;
use PDO;

class UserController
{
    public function __construct(
        private readonly AuthMiddleware $auth  = new AuthMiddleware(),
        private readonly AuditService  $audit = new AuditService(),
        private readonly MailService   $mail  = new MailService()
    ) {}

    public function index(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'users.view');

        $db      = Database::connection();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        $conditions = ['u.is_active = TRUE'];
        $params     = [];

        // Scope: regional admins only see users in their region
        if ($user['role_name'] === 'regional_admin' && $user['region_id']) {
            $conditions[] = '(u.region_id = :region_id OR h.region_id = :region_id2)';
            $params['region_id']  = $user['region_id'];
            $params['region_id2'] = $user['region_id'];
        }

        if (!empty($_GET['hospital_id'])) {
            $conditions[] = 'u.hospital_id = :hospital_id';
            $params['hospital_id'] = $_GET['hospital_id'];
        }
        if (!empty($_GET['role'])) {
            $conditions[] = 'r.name = :role';
            $params['role'] = $_GET['role'];
        }
        if (!empty($_GET['search'])) {
            $conditions[] = "(u.name LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $_GET['search'] . '%';
        }

        $where = implode(' AND ', $conditions);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN hospitals h ON h.id = u.hospital_id WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.is_active, u.mfa_enabled, u.last_login_at, u.created_at,
                   r.name AS role_name, r.display_name AS role_display,
                   h.name AS hospital_name, reg.name AS region_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            LEFT JOIN hospitals h ON h.id = u.hospital_id
            LEFT JOIN regions reg ON reg.id = u.region_id
            WHERE $where
            ORDER BY u.name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            if (!in_array($key, ['limit', 'offset'], true)) $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        Response::paginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function store(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'users.create');

        $body = $this->parseBody();
        $v    = Validator::make($body, [
            'name'    => 'required|string|max:150',
            'email'   => 'required|email|max:200',
            'role_id' => 'required|uuid',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        $db = Database::connection();

        // Check email uniqueness
        $existing = $db->prepare('SELECT id FROM users WHERE email = :email');
        $existing->execute(['email' => strtolower($body['email'])]);
        if ($existing->fetch()) {
            Response::validationError(['email' => ['Email address already in use.']]);
            return;
        }

        // Generate a temporary password
        $tempPassword = $this->generateTempPassword();
        $hash         = password_hash($tempPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);

        $id = $this->generateUuid();
        $db->prepare("
            INSERT INTO users (id, role_id, hospital_id, region_id, name, email, password_hash, phone, must_change_password, email_verified_at)
            VALUES (:id, :role_id, :hospital_id, :region_id, :name, :email, :hash, :phone, TRUE, NOW())
        ")->execute([
            'id'          => $id,
            'role_id'     => $body['role_id'],
            'hospital_id' => $body['hospital_id'] ?? null,
            'region_id'   => $body['region_id'] ?? null,
            'name'        => $body['name'],
            'email'       => strtolower($body['email']),
            'hash'        => $hash,
            'phone'       => $body['phone'] ?? null,
        ]);

        $this->mail->sendWelcome($body['email'], $body['name'], $tempPassword);
        $this->audit->log($user['id'], 'user.create', 'user', $id, null, ['email' => $body['email'], 'role_id' => $body['role_id']]);

        Response::success(['id' => $id], 'User created. A welcome email has been sent.', 201);
    }

    public function show(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'users.view');

        $db   = Database::connection();
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.is_active, u.mfa_enabled, u.last_login_at, u.last_login_ip,
                   u.failed_login_attempts, u.password_changed_at, u.must_change_password, u.created_at,
                   r.name AS role_name, r.display_name AS role_display,
                   h.name AS hospital_name, reg.name AS region_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            LEFT JOIN hospitals h ON h.id = u.hospital_id
            LEFT JOIN regions reg ON reg.id = u.region_id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            Response::notFound('User not found');
            return;
        }

        Response::success($targetUser);
    }

    public function update(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'users.edit');

        $body = $this->parseBody();
        $db   = Database::connection();

        $old = $db->prepare('SELECT * FROM users WHERE id = :id');
        $old->execute(['id' => $id]);
        $old = $old->fetch();

        if (!$old) {
            Response::notFound('User not found');
            return;
        }

        $allowed = ['name', 'phone', 'hospital_id', 'region_id', 'is_active'];
        // Super admins can also change roles
        if ($user['role_name'] === 'super_admin') $allowed[] = 'role_id';

        $sets   = ['updated_at = NOW()'];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]        = "$field = :$field";
                $params[$field] = $body[$field];
            }
        }

        $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')
           ->execute($params);

        $this->audit->log($user['id'], 'user.update', 'user', $id);
        Response::success(null, 'User updated');
    }

    public function deactivate(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'users.delete');

        if ($id === $user['id']) {
            Response::error('You cannot deactivate your own account', 400);
            return;
        }

        Database::connection()->prepare("UPDATE users SET is_active = FALSE, updated_at = NOW() WHERE id = :id")
            ->execute(['id' => $id]);

        $this->audit->log($user['id'], 'user.deactivate', 'user', $id);
        Response::success(null, 'User deactivated');
    }

    private function generateTempPassword(): string
    {
        $chars    = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
