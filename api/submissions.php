<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');
$action = qp('action');

// ── GET single ────────────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("
        SELECT s.*, f.name AS form_name, h.name AS hospital_name,
               u.name AS submitted_by_name, rv.name AS reviewer_name
        FROM submissions s
        JOIN forms f ON f.id = s.form_id
        JOIN hospitals h ON h.id = s.hospital_id
        JOIN users u ON u.id = s.submitted_by
        LEFT JOIN users rv ON rv.id = s.reviewed_by
        WHERE s.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $sub = $stmt->fetch();
    if (!$sub) fail('Submission not found', 404);

    $vals = $db->prepare("
        SELECT sv.field_id, ff.label, ff.field_type, ff.name AS field_name,
               sv.value_text, sv.value_number, sv.value_date, sv.value_boolean
        FROM submission_values sv
        JOIN form_fields ff ON ff.id = sv.field_id
        WHERE sv.submission_id = :id
        ORDER BY ff.sort_order
    ");
    $vals->execute(['id' => $id]);
    $sub['values'] = $vals->fetchAll();
    ok($sub);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page    = max(1, (int) qp('page', 1));
    $perPage = max(1, (int) qp('per_page', 25));
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if ($user['role'] === 'data_entry') {
        $where[] = 's.submitted_by = :uid';
        $params['uid'] = $user['id'];
    } elseif ($user['role'] === 'hospital_admin') {
        $where[] = 's.hospital_id = :hid';
        $params['hid'] = $user['hospital_id'];
    } elseif ($user['role'] === 'regional_admin') {
        $where[] = 'h.region_id = :rid';
        $params['rid'] = $user['region_id'];
    }

    if (qp('form_id'))     { $where[] = 's.form_id = :form_id';     $params['form_id']     = qp('form_id'); }
    if (qp('hospital_id')) { $where[] = 's.hospital_id = :hosp';    $params['hosp']        = qp('hospital_id'); }
    if (qp('status'))      { $where[] = 's.status = :status';       $params['status']      = qp('status'); }
    if (qp('from'))        { $where[] = 's.submitted_at >= :from';   $params['from']        = qp('from'); }
    if (qp('to'))          { $where[] = 's.submitted_at <= :to';     $params['to']          = qp('to'); }
    if (qp('search'))      {
        $where[] = '(f.name LIKE :search OR h.name LIKE :search OR u.name LIKE :search)';
        $params['search'] = '%' . qp('search') . '%';
    }

    $w = implode(' AND ', $where);

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM submissions s
        JOIN forms f ON f.id = s.form_id
        JOIN hospitals h ON h.id = s.hospital_id
        JOIN users u ON u.id = s.submitted_by
        WHERE $w
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params['limit']  = $perPage;
    $params['offset'] = $offset;

    $stmt = $db->prepare("
        SELECT s.id, f.name AS form_name, h.name AS hospital_name, u.name AS submitted_by_name,
               s.status, s.period_start, s.period_end, s.submitted_at, s.created_at
        FROM submissions s
        JOIN forms f ON f.id = s.form_id
        JOIN hospitals h ON h.id = s.hospital_id
        JOIN users u ON u.id = s.submitted_by
        WHERE $w
        ORDER BY s.created_at DESC
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

// ── PUT review ────────────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    need($user, 'review_submissions');
    $b      = body();
    $status = $b['status'] ?? '';
    if (!in_array($status, ['approved', 'rejected'], true)) fail('Status must be approved or rejected');

    $db->prepare("
        UPDATE submissions
        SET status = :status, reviewed_by = :rev, reviewed_at = NOW(),
            review_notes = :notes, updated_at = NOW()
        WHERE id = :id
    ")->execute(['status' => $status, 'rev' => $user['id'], 'notes' => $b['notes'] ?? null, 'id' => $id]);

    ok(null, "Submission $status");
}

// ── POST review (legacy action param) ─────────────────────────────────────────
if ($method === 'POST' && $action === 'review' && $id) {
    need($user, 'review_submissions');
    $b      = body();
    $status = $b['status'] ?? '';
    if (!in_array($status, ['approved', 'rejected'], true)) fail('Status must be approved or rejected');

    $db->prepare("
        UPDATE submissions
        SET status = :status, reviewed_by = :rev, reviewed_at = NOW(),
            review_notes = :notes, updated_at = NOW()
        WHERE id = :id
    ")->execute(['status' => $status, 'rev' => $user['id'], 'notes' => $b['notes'] ?? null, 'id' => $id]);

    ok(null, "Submission $status");
}

// ── POST create ───────────────────────────────────────────────────────────────
if ($method === 'POST') {
    need($user, 'create_submissions');
    $b = body();

    $formId     = $b['form_id'] ?? '';
    $hospitalId = $b['hospital_id'] ?? $user['hospital_id'] ?? '';

    if (!$formId)     fail('form_id is required');
    if (!$hospitalId) fail('hospital_id is required');

    $form = $db->prepare("SELECT * FROM forms WHERE id = :id AND status = 'published'");
    $form->execute(['id' => $formId]);
    $form = $form->fetch();
    if (!$form) fail('Form not found or not published', 404);

    $subId  = uid();
    $status = ($b['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
    $submittedAt = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

    $db->prepare("
        INSERT INTO submissions (id, form_id, hospital_id, submitted_by, status, period_start, period_end, submitted_at)
        VALUES (:id, :form_id, :hospital_id, :user_id, :status, :ps, :pe, :sat)
    ")->execute([
        'id'          => $subId,
        'form_id'     => $formId,
        'hospital_id' => $hospitalId,
        'user_id'     => $user['id'],
        'status'      => $status,
        'ps'          => $b['period_start'] ?? null,
        'pe'          => $b['period_end']   ?? null,
        'sat'         => $submittedAt,
    ]);

    if (!empty($b['values']) && is_array($b['values'])) {
        $valStmt = $db->prepare("
            INSERT INTO submission_values (id, submission_id, field_id, value_text, value_number, value_date, value_boolean)
            VALUES (:id, :sub_id, :field_id, :text, :number, :date, :boolean)
        ");
        foreach ($b['values'] as $fieldId => $value) {
            $fld = $db->prepare('SELECT * FROM form_fields WHERE id = :id');
            $fld->execute(['id' => $fieldId]);
            $fld = $fld->fetch();
            if (!$fld) continue;

            $valStmt->execute([
                'id'      => uid(),
                'sub_id'  => $subId,
                'field_id'=> $fieldId,
                'text'    => ($fld['field_type'] === 'number') ? null : (string)$value,
                'number'  => is_numeric($value) ? $value : null,
                'date'    => $fld['field_type'] === 'date' ? $value : null,
                'boolean' => is_bool($value) ? ($value ? 1 : 0) : null,
            ]);
        }
    }

    ok(['id' => $subId], 'Submission saved', 201);
}

fail('Method not allowed', 405);
