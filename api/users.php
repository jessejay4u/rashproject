<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');

// ── GET single ────────────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    need($user, 'view_users');
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active,
               u.last_login_at, u.created_at,
               h.name AS hospital_name, h.id AS hospital_id,
               reg.name AS region_name, reg.id AS region_id
        FROM users u
        LEFT JOIN hospitals h ON h.id = u.hospital_id
        LEFT JOIN regions reg ON reg.id = u.region_id
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $u = $stmt->fetch();
    if (!$u) fail('User not found', 404);
    ok($u);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    need($user, 'view_users');
    $page    = max(1, (int) qp('page', 1));
    $perPage = max(1, (int) qp('per_page', 25));
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if ($user['role'] === 'regional_admin' && $user['region_id']) {
        $where[] = '(u.region_id = :rid OR h.region_id = :rid2)';
        $params['rid']  = $user['region_id'];
        $params['rid2'] = $user['region_id'];
    }
    if (qp('hospital_id')) { $where[] = 'u.hospital_id = :hid';      $params['hid']  = qp('hospital_id'); }
    if (qp('role'))        { $where[] = 'u.role = :role';             $params['role'] = qp('role'); }
    if (qp('search'))      { $where[] = '(u.name LIKE :s OR u.email LIKE :s)'; $params['s'] = '%' . qp('search') . '%'; }
    if (qp('active') !== null) { $where[] = 'u.is_active = :active'; $params['active'] = qp('active') === '0' ? 0 : 1; }

    $w = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN hospitals h ON h.id = u.hospital_id WHERE $w");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params['limit']  = $perPage;
    $params['offset'] = $offset;

    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.last_login_at, u.created_at,
               h.name AS hospital_name, reg.name AS region_name
        FROM users u
        LEFT JOIN hospitals h ON h.id = u.hospital_id
        LEFT JOIN regions reg ON reg.id = u.region_id
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
    need($user, 'create_users');
    $b = body();

    if (empty($b['name']))  fail('Name is required');
    if (empty($b['email'])) fail('Email is required');
    if (empty($b['role']))  fail('Role is required');
    if (empty($b['password'])) fail('Password is required');

    $email = strtolower(trim($b['email']));

    $existing = $db->prepare('SELECT id FROM users WHERE email = :email');
    $existing->execute(['email' => $email]);
    if ($existing->fetch()) fail('Email address already in use');

    $hash = password_hash($b['password'], PASSWORD_DEFAULT);
    $newId = uid();

    $db->prepare("
        INSERT INTO users (id, role, hospital_id, region_id, name, email, password_hash, phone)
        VALUES (:id, :role, :hid, :regid, :name, :email, :hash, :phone)
    ")->execute([
        'id'    => $newId,
        'role'  => $b['role'],
        'hid'   => $b['hospital_id'] ?? null,
        'regid' => $b['region_id']   ?? null,
        'name'  => $b['name'],
        'email' => $email,
        'hash'  => $hash,
        'phone' => $b['phone'] ?? null,
    ]);

    ok(['id' => $newId], 'User created', 201);
}

// ── PUT/PATCH update ──────────────────────────────────────────────────────────
if (($method === 'PUT' || $method === 'PATCH') && $id) {
    need($user, 'edit_users');
    $b = body();

    $sets   = ['updated_at = NOW()'];
    $params = ['id' => $id];

    $allowed = ['name', 'phone', 'hospital_id', 'region_id', 'is_active'];
    if (in_array($user['role'], ['super_admin'], true)) $allowed[] = 'role';

    foreach ($allowed as $field) {
        if (array_key_exists($field, $b)) {
            $sets[]         = "$field = :$field";
            $params[$field] = $b[$field];
        }
    }

    if (!empty($b['password'])) {
        $sets[]              = 'password_hash = :hash';
        $params['hash']      = password_hash($b['password'], PASSWORD_DEFAULT);
    }

    if (array_key_exists('status', $b)) {
        $sets[]            = 'is_active = :is_active_s';
        $params['is_active_s'] = $b['status'] === 'active' ? 1 : 0;
    }

    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id')
       ->execute($params);

    ok(null, 'User updated');
}

// ── DELETE deactivate ─────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    need($user, 'deactivate_users');
    if ($id === $user['id']) fail('You cannot deactivate your own account');
    $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :id")->execute(['id' => $id]);
    ok(null, 'User deactivated');
}

fail('Method not allowed', 405);
