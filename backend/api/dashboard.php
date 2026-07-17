<?php
require_once __DIR__ . '/bootstrap.php';
$user = auth();
$db   = db();

// Scope filter
$scopeWhere  = '1=1';
$scopeParams = [];
if ($user['role'] === 'data_entry' || $user['role'] === 'hospital_admin') {
    $scopeWhere  = 's.hospital_id = :hid';
    $scopeParams = ['hid' => $user['hospital_id']];
} elseif ($user['role'] === 'regional_admin') {
    $scopeWhere  = 'h.region_id = :rid';
    $scopeParams = ['rid' => $user['region_id']];
}

function count_query(PDO $db, string $sql, array $params = []): int {
    $s = $db->prepare($sql);
    $s->execute($params);
    return (int) $s->fetchColumn();
}

// Hospital/user totals scope: same role split as submissions, applied to the hospitals/users tables directly.
// Columns are qualified with h./u. since byHosp joins hospitals+regions (both have is_active).
$hospScopeWhere  = 'h.is_active = 1';
$userScopeWhere  = 'u.is_active = 1';
$hospScopeParams = [];
$userScopeParams = [];
if ($user['role'] === 'data_entry' || $user['role'] === 'hospital_admin') {
    $hospScopeWhere  = 'h.is_active = 1 AND h.id = :hid';
    $userScopeWhere  = 'u.is_active = 1 AND u.hospital_id = :hid';
    $hospScopeParams = ['hid' => $user['hospital_id']];
    $userScopeParams = ['hid' => $user['hospital_id']];
} elseif ($user['role'] === 'regional_admin') {
    $hospScopeWhere  = 'h.is_active = 1 AND h.region_id = :rid';
    $userScopeWhere  = 'u.is_active = 1 AND (u.region_id = :rid OR u.hospital_id IN (SELECT id FROM hospitals WHERE region_id = :rid2))';
    $hospScopeParams = ['rid' => $user['region_id']];
    $userScopeParams = ['rid' => $user['region_id'], 'rid2' => $user['region_id']];
}

$stats = [
    'total_submissions' => count_query($db, "SELECT COUNT(*) FROM submissions s LEFT JOIN hospitals h ON h.id = s.hospital_id WHERE $scopeWhere", $scopeParams),
    'pending_review'    => count_query($db, "SELECT COUNT(*) FROM submissions s LEFT JOIN hospitals h ON h.id = s.hospital_id WHERE s.status = 'submitted' AND $scopeWhere", $scopeParams),
    'approved'          => count_query($db, "SELECT COUNT(*) FROM submissions s LEFT JOIN hospitals h ON h.id = s.hospital_id WHERE s.status = 'approved' AND $scopeWhere", $scopeParams),
    'rejected'          => count_query($db, "SELECT COUNT(*) FROM submissions s LEFT JOIN hospitals h ON h.id = s.hospital_id WHERE s.status = 'rejected' AND $scopeWhere", $scopeParams),
    'total_hospitals'   => count_query($db, "SELECT COUNT(*) FROM hospitals h WHERE $hospScopeWhere", $hospScopeParams),
    'total_forms'       => count_query($db, "SELECT COUNT(*) FROM forms WHERE status = 'published'"),
    'total_users'       => count_query($db, "SELECT COUNT(*) FROM users u WHERE $userScopeWhere", $userScopeParams),
    'this_month'        => count_query($db, "SELECT COUNT(*) FROM submissions s LEFT JOIN hospitals h ON h.id = s.hospital_id WHERE submitted_at >= DATE_FORMAT(NOW(),'%Y-%m-01') AND $scopeWhere", $scopeParams),
];

// Recent submissions
$recentStmt = $db->prepare("
    SELECT s.id, f.name AS form, h.name AS hospital, u.name AS submitted_by,
           s.status, s.submitted_at
    FROM submissions s
    JOIN forms f ON f.id = s.form_id
    JOIN hospitals h ON h.id = s.hospital_id
    JOIN users u ON u.id = s.submitted_by
    WHERE $scopeWhere
    ORDER BY s.created_at DESC
    LIMIT 10
");
$recentStmt->execute($scopeParams);
$recent = $recentStmt->fetchAll();

// Submissions by day (last 30 days)
$trendStmt = $db->prepare("
    SELECT DATE(s.submitted_at) AS day, COUNT(*) AS count
    FROM submissions s
    LEFT JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND s.status != 'draft'
      AND $scopeWhere
    GROUP BY day
    ORDER BY day
");
$trendStmt->execute($scopeParams);
$trend = $trendStmt->fetchAll();

// Submissions by status
$byStatusStmt = $db->prepare("
    SELECT s.status, COUNT(*) AS count
    FROM submissions s
    LEFT JOIN hospitals h ON h.id = s.hospital_id
    WHERE $scopeWhere
    GROUP BY s.status
");
$byStatusStmt->execute($scopeParams);
$byStatus = $byStatusStmt->fetchAll();

// Submissions by form (top 10)
$byFormStmt = $db->prepare("
    SELECT f.name AS form_name, COUNT(s.id) AS count
    FROM submissions s
    JOIN forms f ON f.id = s.form_id
    LEFT JOIN hospitals h ON h.id = s.hospital_id
    WHERE $scopeWhere
    GROUP BY f.id, f.name
    ORDER BY count DESC
    LIMIT 10
");
$byFormStmt->execute($scopeParams);
$byForm = $byFormStmt->fetchAll();

// Submissions by hospital (top 15)
$byHospStmt = $db->prepare("
    SELECT h.name AS hospital_name, r.name AS region_name,
           COUNT(s.id) AS total,
           SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) AS approved,
           SUM(CASE WHEN s.status = 'submitted' THEN 1 ELSE 0 END) AS pending
    FROM hospitals h
    LEFT JOIN submissions s ON s.hospital_id = h.id
    LEFT JOIN regions r ON r.id = h.region_id
    WHERE $hospScopeWhere
    GROUP BY h.id, h.name, r.name
    ORDER BY total DESC
    LIMIT 15
");
$byHospStmt->execute($hospScopeParams);
$byHosp = $byHospStmt->fetchAll();

ok(compact('stats', 'recent', 'trend', 'byStatus', 'byForm') + ['byHospital' => $byHosp]);
