<?php
require_once __DIR__ . '/bootstrap.php';

$user   = auth();
$db     = db();
$action = qp('action', 'export');
$format = qp('format', 'csv');

need($user, 'export_reports');

if ($user['role'] === 'regional_admin' && $user['region_id']) {
    $where[] = 'h.region_id = :rid_scope';
    $params['rid_scope'] = $user['region_id'];
}

$where  = ['1=1'];
$params = [];

if (qp('form_id'))     { $where[] = 's.form_id = :form_id';        $params['form_id']     = qp('form_id'); }
if (qp('hospital_id')) { $where[] = 's.hospital_id = :hospital_id'; $params['hospital_id'] = qp('hospital_id'); }
if (qp('from'))        { $where[] = 's.submitted_at >= :from';       $params['from']        = qp('from'); }
if (qp('to'))          { $where[] = 's.submitted_at <= :to';         $params['to']          = qp('to'); }
if (qp('status'))      { $where[] = 's.status = :status';            $params['status']      = qp('status'); }

// Scope
if ($user['role'] === 'data_entry')    { $where[] = 's.submitted_by = :uid'; $params['uid'] = $user['id']; }
if ($user['role'] === 'hospital_admin'){ $where[] = 's.hospital_id = :hid';  $params['hid'] = $user['hospital_id']; }

$w = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT s.id, f.name AS form, h.name AS hospital, r.name AS region,
           u.name AS submitted_by, s.status, s.period_start, s.period_end,
           s.submitted_at, s.reviewed_at, s.review_notes
    FROM submissions s
    JOIN forms f ON f.id = s.form_id
    JOIN hospitals h ON h.id = s.hospital_id
    JOIN regions r ON r.id = h.region_id
    JOIN users u ON u.id = s.submitted_by
    WHERE $w
    ORDER BY s.submitted_at DESC
    LIMIT 10000
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="submissions_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
if (!empty($rows)) {
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) fputcsv($out, $row);
}
fclose($out);
exit;
