<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');
$action = qp('action');

function insertFormSections(PDO $db, string $formId, array $sections): void {
    foreach ($sections as $si => $section) {
        if (empty($section['title'])) continue;
        $sectionId = uid();
        $db->prepare("
            INSERT INTO form_sections (id, form_id, title, description, order_index, is_repeatable)
            VALUES (:id, :fid, :title, :desc, :ord, :rep)
        ")->execute([
            'id'    => $sectionId,
            'fid'   => $formId,
            'title' => $section['title'],
            'desc'  => $section['description'] ?? null,
            'ord'   => $si,
            'rep'   => !empty($section['is_repeatable']) ? 1 : 0,
        ]);

        foreach ($section['fields'] ?? [] as $fi => $field) {
            if (empty($field['label'])) continue;
            $db->prepare("
                INSERT INTO form_fields (id, section_id, form_id, name, label, field_type, is_required, order_index, placeholder, help_text, options, validation)
                VALUES (:id, :sid, :fid, :name, :label, :type, :req, :ord, :ph, :help, :opts, :val)
            ")->execute([
                'id'    => uid(),
                'sid'   => $sectionId,
                'fid'   => $formId,
                'name'  => strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $field['name'] ?: $field['label'])),
                'label' => $field['label'],
                'type'  => $field['field_type'] ?? 'text',
                'req'   => !empty($field['is_required']) ? 1 : 0,
                'ord'   => $fi,
                'ph'    => $field['placeholder'] ?? null,
                'help'  => $field['help_text'] ?? null,
                'opts'  => json_encode($field['options'] ?? []),
                'val'   => json_encode($field['validation'] ?? []),
            ]);
        }
    }
}

// ── GET single form with sections + fields ────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare('
        SELECT f.*, u.name AS created_by_name,
               (SELECT COUNT(*) FROM submissions WHERE form_id = f.id) AS submission_count
        FROM forms f JOIN users u ON u.id = f.created_by WHERE f.id = :id
    ');
    $stmt->execute(['id' => $id]);
    $form = $stmt->fetch();
    if (!$form) fail('Form not found', 404);

    $sections = $db->prepare('SELECT * FROM form_sections WHERE form_id = :id ORDER BY order_index');
    $sections->execute(['id' => $id]);
    $sections = $sections->fetchAll();

    $fields = $db->prepare('SELECT * FROM form_fields WHERE form_id = :id ORDER BY order_index');
    $fields->execute(['id' => $id]);
    $allFields = $fields->fetchAll();

    foreach ($sections as &$s) {
        $s['fields'] = array_values(array_filter($allFields, fn($f) => $f['section_id'] === $s['id']));
        foreach ($s['fields'] as &$f) {
            $f['options']    = json_decode($f['options']    ?? '[]', true);
            $f['validation'] = json_decode($f['validation'] ?? '{}', true);
        }
    }

    $form['sections'] = $sections;
    $form['settings'] = json_decode($form['settings'] ?? '{}', true);
    ok($form);
}

// ── GET list ─────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page    = max(1, (int) qp('page', 1));
    $perPage = 25;
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
        SELECT f.id, f.name, f.code, f.category, f.status, f.version, f.published_at, f.created_at,
               u.name AS created_by_name,
               (SELECT COUNT(*) FROM form_fields WHERE form_id = f.id) AS field_count,
               (SELECT COUNT(*) FROM submissions WHERE form_id = f.id) AS submission_count
        FROM forms f
        JOIN users u ON u.id = f.created_by
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
    need($user, 'forms.create');
    $b = body();
    if (empty($b['name'])) fail('Name is required');
    if (empty($b['code'])) fail('Code is required');

    $formId = uid();
    $code   = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $b['code']));

    $db->prepare("
        INSERT INTO forms (id, created_by, name, code, description, category, version, status, is_recurring, settings)
        VALUES (:id, :uid, :name, :code, :desc, :cat, 1, 'draft', :rec, :settings)
    ")->execute([
        'id'       => $formId,
        'uid'      => $user['id'],
        'name'     => $b['name'],
        'code'     => $code,
        'desc'     => $b['description'] ?? null,
        'cat'      => $b['category'] ?? null,
        'rec'      => !empty($b['is_recurring']) ? 1 : 0,
        'settings' => json_encode($b['settings'] ?? []),
    ]);

    insertFormSections($db, $formId, $b['sections'] ?? []);

    ok(['id' => $formId], 'Form created', 201);
}

// ── PUT/PATCH edit ──────────────────────────────────────────────────────────────
if (($method === 'PUT' || $method === 'PATCH') && $id) {
    need($user, 'forms.edit');
    $b = body();
    if (empty($b['name'])) fail('Name is required');

    $exists = $db->prepare('SELECT id FROM forms WHERE id = :id');
    $exists->execute(['id' => $id]);
    if (!$exists->fetch()) fail('Form not found', 404);

    $db->prepare("
        UPDATE forms SET name = :name, description = :desc, category = :cat, is_recurring = :rec, updated_at = NOW()
        WHERE id = :id
    ")->execute([
        'name' => $b['name'],
        'desc' => $b['description'] ?? null,
        'cat'  => $b['category'] ?? null,
        'rec'  => !empty($b['is_recurring']) ? 1 : 0,
        'id'   => $id,
    ]);

    if (isset($b['sections'])) {
        // Full replace: form_fields cascades on form_sections delete.
        $db->prepare('DELETE FROM form_sections WHERE form_id = :id')->execute(['id' => $id]);
        insertFormSections($db, $id, $b['sections']);
    }

    ok(null, 'Form updated');
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    need($user, 'forms.delete');

    $cnt = $db->prepare('SELECT COUNT(*) FROM submissions WHERE form_id = :id');
    $cnt->execute(['id' => $id]);
    if ((int)$cnt->fetchColumn() > 0) {
        fail('This form has submissions and cannot be deleted. Archive it instead.', 409);
    }

    $del = $db->prepare('DELETE FROM forms WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) fail('Form not found', 404);

    ok(null, 'Form deleted');
}

// ── POST publish ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'publish' && $id) {
    need($user, 'forms.publish');
    $db->prepare("UPDATE forms SET status = 'published', published_at = NOW(), updated_at = NOW() WHERE id = :id")
       ->execute(['id' => $id]);
    ok(null, 'Form published');
}

// ── POST archive ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'archive' && $id) {
    need($user, 'forms.edit');
    $db->prepare("UPDATE forms SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = :id")
       ->execute(['id' => $id]);
    ok(null, 'Form archived');
}

fail('Method not allowed', 405);
