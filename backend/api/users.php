<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');

// ── GET single ────────────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    need($user, 'users.view');
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.is_active, u.mfa_enabled,
               u.last_login_at, u.created_at, u.must_change_password, u.failed_login_attempts,
               u.role_id, u.hospital_id, u.region_id, r.name AS role, r.display_name AS role_display,
               h.name AS hospital_name, h.region_id AS hospital_region_id, reg.name AS region_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        LEFT JOIN hospitals h ON h.id = u.hospital_id
        LEFT JOIN regions reg ON reg.id = u.region_id
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $u = $stmt->fetch();
    if (!$u) fail('User not found', 404);
    if ($user['role'] === 'regional_admin') {
        $inRegion = $u['region_id'] === $user['region_id'] || $u['hospital_region_id'] === $user['region_id'];
        if (!$inRegion) fail('User not found', 404);
    }
    ok($u);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    need($user, 'users.view');
    $page    = max(1, (int) qp('page', 1));
    $perPage = 25;
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if ($user['role'] === 'regional_admin' && $user['region_id']) {
        $where[] = '(u.region_id = :rid OR h.region_id = :rid2)';
        $params['rid']  = $user['region_id'];
        $params['rid2'] = $user['region_id'];
    }
    if (qp('hospital_id')) { $where[] = 'u.hospital_id = :hid';  $params['hid']  = qp('hospital_id'); }
    if (qp('role'))        { $where[] = 'r.name = :role';         $params['role'] = qp('role'); }
    if (qp('search'))      { $where[] = '(u.name LIKE :s OR u.email LIKE :s)'; $params['s'] = '%' . qp('search') . '%'; }
    if (qp('active') !== null) { $where[] = 'u.is_active = :active'; $params['active'] = qp('active') === '0' ? 0 : 1; }

    $w = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN hospitals h ON h.id = u.hospital_id WHERE $w");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params['limit']  = $perPage;
    $params['offset'] = $offset;

    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.is_active, u.last_login_at, u.created_at,
               u.role_id, r.name AS role, r.display_name AS role_display,
               h.name AS hospital_name, COALESCE(reg.name, hreg.name) AS region_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        LEFT JOIN hospitals h ON h.id = u.hospital_id
        LEFT JOIN regions reg  ON reg.id  = u.region_id
        LEFT JOIN regions hreg ON hreg.id = h.region_id
        WHERE $w
        ORDER BY u.name
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue('limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset,  PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        if (!in_array($k, ['limit', 'offset'], true)) $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    paginate($stmt->fetchAll(), $total, $page, $perPage);
}

// ── POST create ───────────────────────────────────────────────────────────────
if ($method === 'POST') {
    need($user, 'users.create');
    $b = body();

    if (empty($b['name']))    fail('Name is required');
    if (empty($b['email']))   fail('Email is required');
    if (empty($b['role_id'])) fail('Role is required');

    $email = strtolower(trim($b['email']));

    $existing = $db->prepare('SELECT id FROM users WHERE email = :email');
    $existing->execute(['email' => $email]);
    if ($existing->fetch()) fail('Email address already in use');

    $tempPass = generateTempPassword();
    $hash     = password_hash($tempPass, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
    $id       = uid();

    $db->prepare("
        INSERT INTO users (id, role_id, hospital_id, region_id, name, email, password_hash, phone, must_change_password, email_verified_at)
        VALUES (:id, :rid, :hid, :regid, :name, :email, :hash, :phone, 1, NOW())
    ")->execute([
        'id'    => $id,
        'rid'   => $b['role_id'],
        'hid'   => $b['hospital_id'] ?? null,
        'regid' => $b['region_id']   ?? null,
        'name'  => $b['name'],
        'email' => $email,
        'hash'  => $hash,
        'phone' => $b['phone'] ?? null,
    ]);

    ok(['id' => $id, 'temp_password' => $tempPass], 'User created. Share the temporary password with them.', 201);
}

// ── PUT/PATCH update ──────────────────────────────────────────────────────────
if (($method === 'PUT' || $method === 'PATCH') && $id) {
    need($user, 'users.edit');
    $b = body();

    $allowed = ['name', 'phone', 'hospital_id', 'region_id', 'is_active'];
    if ($user['role'] === 'super_admin') $allowed[] = 'role_id';

    $sets   = ['updated_at = NOW()'];
    $params = ['id' => $id];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $b)) {
            $sets[]        = "$field = :$field";
            $params[$field] = $b[$field];
        }
    }

    if (!empty($b['password'])) {
        $newPass = (string) $b['password'];
        if (strlen($newPass) < 8) fail('Password must be at least 8 characters');
        $sets[]             = 'password_hash = :password_hash';
        $sets[]             = 'must_change_password = 1';
        $params['password_hash'] = password_hash($newPass, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
    }

    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')
       ->execute($params);

    ok(null, 'User updated');
}

// ── DELETE deactivate ─────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    need($user, 'users.delete');
    if ($id === $user['id']) fail('You cannot deactivate your own account');
    $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :id")->execute(['id' => $id]);
    ok(null, 'User deactivated');
}

// ── GET roles list (helper) ───────────────────────────────────────────────────
function generateTempPassword(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
    $pass  = '';
    for ($i = 0; $i < 12; $i++) $pass .= $chars[random_int(0, strlen($chars) - 1)];
    return $pass;
}

fail('Method not allowed', 405);
