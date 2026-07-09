<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');
$action = qp('action');

// ── GET single form with sections + fields ────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare('SELECT f.*, u.name AS created_by_name FROM forms f LEFT JOIN users u ON u.id = f.created_by WHERE f.id = :id');
    $stmt->execute(['id' => $id]);
    $form = $stmt->fetch();
    if (!$form) fail('Form not found', 404);

    $sections = $db->prepare('SELECT * FROM form_sections WHERE form_id = :id ORDER BY sort_order');
    $sections->execute(['id' => $id]);
    $sections = $sections->fetchAll();

    $fields = $db->prepare('SELECT * FROM form_fields WHERE form_id = :id ORDER BY sort_order');
    $fields->execute(['id' => $id]);
    $allFields = $fields->fetchAll();

    foreach ($sections as &$s) {
        $s['fields'] = array_values(array_filter($allFields, fn($f) => $f['section_id'] === $s['id']));
        foreach ($s['fields'] as &$f) {
            $f['options']    = json_decode($f['options']    ?? '[]', true);
            $f['validation'] = json_decode($f['validation'] ?? '{}', true);
        }
    }

    $form['sections']   = $sections;
    $form['field_count'] = count($allFields);
    ok($form);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page    = max(1, (int) qp('page', 1));
    $perPage = max(1, (int) qp('per_page', 25));
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];

    if (!in_array($user['role'], ['super_admin', 'regional_admin'], true)) {
        $where[] = "f.status = 'published'";
    }
    if (qp('status'))   { $where[] = 'f.status = :status';       $params['status']   = qp('status'); }
    if (qp('category')) { $where[] = 'f.category = :category';   $params['category'] = qp('category'); }
    if (qp('search'))   { $where[] = 'f.name LIKE :search';      $params['search']   = '%' . qp('search') . '%'; }

    $w = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM forms f WHERE $w");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $params['limit']  = $perPage;
    $params['offset'] = $offset;

    $stmt = $db->prepare("
        SELECT f.id, f.name, f.code, f.category, f.status, f.version, f.description, f.published_at, f.created_at,
               u.name AS created_by_name,
               (SELECT COUNT(*) FROM form_fields WHERE form_id = f.id) AS field_count,
               (SELECT COUNT(*) FROM submissions WHERE form_id = f.id) AS submission_count
        FROM forms f
        LEFT JOIN users u ON u.id = f.created_by
        WHERE $w
        ORDER BY f.updated_at DESC
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
if ($method === 'POST' && !$action) {
    need($user, 'create_forms');
    $b = body();
    if (empty($b['name'])) fail('Name is required');

    $formId = uid();
    $code   = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $b['code'] ?? $b['name']));

    $db->prepare("
        INSERT INTO forms (id, created_by, name, code, description, category, version, status, is_recurring)
        VALUES (:id, :uid, :name, :code, :desc, :cat, 1, :status, :rec)
    ")->execute([
        'id'     => $formId,
        'uid'    => $user['id'],
        'name'   => $b['name'],
        'code'   => $code,
        'desc'   => $b['description'] ?? null,
        'cat'    => $b['category']    ?? null,
        'status' => in_array($b['status'] ?? '', ['draft','published','archived']) ? $b['status'] : 'draft',
        'rec'    => !empty($b['is_recurring']) ? 1 : 0,
    ]);

    // Accept either sections[] or flat fields[]
    if (!empty($b['sections']) && is_array($b['sections'])) {
        foreach ($b['sections'] as $si => $section) {
            $sectionId = uid();
            $db->prepare("INSERT INTO form_sections (id, form_id, title, description, sort_order) VALUES (:id, :fid, :title, :desc, :ord)")
               ->execute(['id'=>$sectionId,'fid'=>$formId,'title'=>$section['title']??'Section '.($si+1),'desc'=>$section['description']??null,'ord'=>$si]);
            foreach ($section['fields'] ?? [] as $fi => $field) {
                _insertField($db, $formId, $sectionId, $field, $fi);
            }
        }
    } elseif (!empty($b['fields']) && is_array($b['fields'])) {
        $sectionId = uid();
        $db->prepare("INSERT INTO form_sections (id, form_id, title, sort_order) VALUES (:id, :fid, :title, 0)")
           ->execute(['id'=>$sectionId,'fid'=>$formId,'title'=>'Fields']);
        foreach ($b['fields'] as $fi => $field) {
            _insertField($db, $formId, $sectionId, $field, $fi);
        }
    }

    ok(['id' => $formId], 'Form created', 201);
}

// ── PUT update ────────────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    need($user, 'edit_forms');
    $b = body();
    if (empty($b['name'])) fail('Name is required');

    $db->prepare("UPDATE forms SET name=:name, description=:desc, status=:status, updated_at=NOW() WHERE id=:id")
       ->execute([
           'name'   => $b['name'],
           'desc'   => $b['description'] ?? null,
           'status' => in_array($b['status'] ?? '', ['draft','published','archived']) ? $b['status'] : 'draft',
           'id'     => $id,
       ]);

    // Rebuild fields if provided
    if (!empty($b['fields']) && is_array($b['fields'])) {
        // Delete existing fields and sections, then recreate
        $db->prepare("DELETE FROM form_fields WHERE form_id = :id")->execute(['id' => $id]);
        $db->prepare("DELETE FROM form_sections WHERE form_id = :id")->execute(['id' => $id]);

        $sectionId = uid();
        $db->prepare("INSERT INTO form_sections (id, form_id, title, sort_order) VALUES (:id, :fid, :title, 0)")
           ->execute(['id'=>$sectionId,'fid'=>$id,'title'=>'Fields']);
        foreach ($b['fields'] as $fi => $field) {
            _insertField($db, $id, $sectionId, $field, $fi);
        }
    }

    ok(null, 'Form updated');
}

// ── POST publish ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'publish' && $id) {
    need($user, 'publish_forms');
    $db->prepare("UPDATE forms SET status='published', published_at=NOW(), updated_at=NOW() WHERE id=:id")
       ->execute(['id' => $id]);
    ok(null, 'Form published');
}

// ── POST archive ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'archive' && $id) {
    need($user, 'edit_forms');
    $db->prepare("UPDATE forms SET status='archived', updated_at=NOW() WHERE id=:id")
       ->execute(['id' => $id]);
    ok(null, 'Form archived');
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    need($user, 'edit_forms');
    $subCount = $db->prepare('SELECT COUNT(*) FROM submissions WHERE form_id = :id');
    $subCount->execute(['id' => $id]);
    if ((int)$subCount->fetchColumn() > 0) fail('Cannot delete form with existing submissions');
    $db->prepare("DELETE FROM forms WHERE id = :id")->execute(['id' => $id]);
    ok(null, 'Form deleted');
}

fail('Method not allowed', 405);

function _insertField(PDO $db, string $formId, string $sectionId, array $field, int $order): void {
    $db->prepare("
        INSERT INTO form_fields (id, section_id, form_id, name, label, field_type, is_required, sort_order, help_text, options, validation)
        VALUES (:id, :sid, :fid, :name, :label, :type, :req, :ord, :help, :opts, :val)
    ")->execute([
        'id'    => uid(),
        'sid'   => $sectionId,
        'fid'   => $formId,
        'name'  => strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $field['name'] ?? 'field_'.$order)),
        'label' => $field['label'] ?? $field['name'] ?? 'Field',
        'type'  => $field['field_type'] ?? $field['type'] ?? 'text',
        'req'   => !empty($field['is_required']) || !empty($field['required']) ? 1 : 0,
        'ord'   => $order,
        'help'  => $field['help_text'] ?? null,
        'opts'  => json_encode($field['options'] ?? []),
        'val'   => json_encode($field['validation'] ?? []),
    ]);
}
