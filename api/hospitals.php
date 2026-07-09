<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');

// ── GET single ────────────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("
        SELECT h.*, r.name AS region_name,
               (SELECT COUNT(*) FROM users WHERE hospital_id = h.id AND is_active = 1) AS user_count,
               (SELECT COUNT(*) FROM submissions WHERE hospital_id = h.id) AS total_submissions,
               (SELECT MAX(submitted_at) FROM submissions WHERE hospital_id = h.id) AS last_submission
        FROM hospitals h
        LEFT JOIN regions r ON r.id = h.region_id
        WHERE h.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $h = $stmt->fetch();
    if (!$h) fail('Hospital not found', 404);
    ok($h);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page    = max(1, (int) qp('page', 1));
    $perPage = max(1, (int) qp('per_page', 25));
    $offset  = ($page - 1) * $perPage;

    $where  = ['h.is_active = 1'];
    $params = [];

    if ($user['role'] === 'regional_admin' && $user['region_id']) {
        $where[] = 'h.region_id = :rid';
        $params['rid'] = $user['region_id'];
    }
    if (qp('region_id')) { $where[] = 'h.region_id = :rid2';  $params['rid2'] = qp('region_id'); }
    if (qp('type'))      { $where[] = 'h.type = :type';        $params['type'] = qp('type'); }
    if (qp('search'))    { $where[] = '(h.name LIKE :s OR h.code LIKE :s)'; $params['s'] = '%' . qp('search') . '%'; }

    $w = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM hospitals h WHERE $w");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params['limit']  = $perPage;
    $params['offset'] = $offset;

    $stmt = $db->prepare("
        SELECT h.id, h.name, h.code, h.type, h.phone, h.email, h.bed_count, h.is_active,
               r.name AS region_name,
               (SELECT COUNT(*) FROM submissions s WHERE s.hospital_id = h.id AND s.submitted_at >= NOW() - INTERVAL 30 DAY) AS monthly_submissions,
               (SELECT MAX(submitted_at) FROM submissions WHERE hospital_id = h.id) AS last_submission
        FROM hospitals h
        LEFT JOIN regions r ON r.id = h.region_id
        WHERE $w
        ORDER BY h.name
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
    need($user, 'create_hospitals');
    $b = body();
    if (empty($b['name']))      fail('Name is required');
    if (empty($b['code']))      fail('Code is required');
    if (empty($b['region_id'])) fail('Region is required');
    if (empty($b['type']))      fail('Type is required');

    $newId = uid();
    $db->prepare("
        INSERT INTO hospitals (id, region_id, name, code, type, address, phone, email, latitude, longitude, bed_count)
        VALUES (:id, :rid, :name, :code, :type, :addr, :phone, :email, :lat, :lon, :beds)
    ")->execute([
        'id'    => $newId,
        'rid'   => $b['region_id'],
        'name'  => $b['name'],
        'code'  => strtoupper($b['code']),
        'type'  => $b['type'],
        'addr'  => $b['address'] ?? null,
        'phone' => $b['phone']   ?? null,
        'email' => $b['email']   ?? null,
        'lat'   => $b['latitude'] ?? null,
        'lon'   => $b['longitude'] ?? null,
        'beds'  => $b['bed_count'] ?? $b['beds'] ?? 0,
    ]);

    ok(['id' => $newId], 'Hospital created', 201);
}

// ── PUT/PATCH update ──────────────────────────────────────────────────────────
if (($method === 'PUT' || $method === 'PATCH') && $id) {
    need($user, 'edit_hospitals');
    $b = body();

    $sets   = ['updated_at = NOW()'];
    $params = ['id' => $id];

    $allowed = ['name', 'type', 'address', 'phone', 'email', 'latitude', 'longitude', 'is_active'];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $b)) {
            $sets[]         = "$field = :$field";
            $params[$field] = $b[$field];
        }
    }

    // Accept either bed_count or beds from frontend
    $bedVal = $b['bed_count'] ?? $b['beds'] ?? null;
    if ($bedVal !== null) {
        $sets[]              = 'bed_count = :bed_count';
        $params['bed_count'] = $bedVal;
    }

    if (array_key_exists('status', $b)) {
        $sets[]            = 'is_active = :is_active_s';
        $params['is_active_s'] = $b['status'] === 'active' ? 1 : 0;
    }

    $db->prepare('UPDATE hospitals SET ' . implode(', ', $sets) . ' WHERE id = :id')
       ->execute($params);

    ok(null, 'Hospital updated');
}

// ── DELETE (deactivate) ───────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    need($user, 'edit_hospitals');
    $db->prepare("UPDATE hospitals SET is_active = 0, updated_at = NOW() WHERE id = :id")->execute(['id' => $id]);
    ok(null, 'Hospital deactivated');
}

fail('Method not allowed', 405);
