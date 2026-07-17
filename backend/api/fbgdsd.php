<?php
require_once __DIR__ . '/bootstrap.php';
$user = auth();
$db   = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');
$action = qp('action');

// ─── Scope helpers ──────────────────────────────────────────────────────────
// Same role-based data boundary as fbg.php: data_entry/hospital_admin pinned to
// their own hospital, regional_admin pinned to hospitals within their region.
function fbgdsdScope(array $user): array {
    if (in_array($user['role'], ['data_entry', 'hospital_admin'], true)) {
        return ['sql' => 'g.hospital_id = :scope_hid', 'params' => ['scope_hid' => $user['hospital_id']]];
    }
    if ($user['role'] === 'regional_admin') {
        return ['sql' => 'h.region_id = :scope_rid', 'params' => ['scope_rid' => $user['region_id']]];
    }
    return ['sql' => '1=1', 'params' => []];
}

function fbgdsdAssertHospitalInScope(array $user, PDO $db, ?string $hospitalId): void {
    if (in_array($user['role'], ['data_entry', 'hospital_admin'], true)) {
        if ($hospitalId !== $user['hospital_id']) fail('Access denied for this facility', 403);
        return;
    }
    if ($user['role'] === 'regional_admin') {
        if (!$hospitalId) fail('hospital_id is required');
        $s = $db->prepare('SELECT region_id FROM hospitals WHERE id = :id');
        $s->execute(['id' => $hospitalId]);
        $region = $s->fetchColumn();
        if ($region === false || $region !== $user['region_id']) fail('Access denied for this facility', 403);
    }
}

// ─── Targets ──────────────────────────────────────────────────────────────────
if (qp('resource') === 'targets') {
    if ($method === 'GET') {
        $scope = fbgdsdScope($user);
        if ($scope['sql'] === '1=1') {
            $rows = $db->query("SELECT * FROM fbgdsd_targets ORDER BY year DESC")->fetchAll();
        } else {
            $scopeSql = str_replace('g.hospital_id', 't.hospital_id', $scope['sql']);
            $stmt = $db->prepare("
                SELECT t.* FROM fbgdsd_targets t
                LEFT JOIN hospitals h ON h.id = t.hospital_id
                WHERE $scopeSql
                ORDER BY t.year DESC
            ");
            $stmt->execute($scope['params']);
            $rows = $stmt->fetchAll();
        }
        ok($rows);
    }
    if ($method === 'POST') {
        need($user, 'submissions.create');
        $b = body();
        fbgdsdAssertHospitalInScope($user, $db, $b['hospital_id'] ?? null);
        $s = $db->prepare("INSERT INTO fbgdsd_targets (id,hospital_id,year,total_groups,beneficiaries_per_group,meetings_per_group)
                           VALUES (:id,:hid,:yr,:tg,:bpg,:mpg)
                           ON DUPLICATE KEY UPDATE total_groups=:tg2,beneficiaries_per_group=:bpg2,meetings_per_group=:mpg2,updated_at=NOW()");
        $s->execute([
            'id'  => uid(), 'hid' => $b['hospital_id'] ?? null,
            'yr'  => (int)($b['year'] ?? date('Y')),
            'tg'  => (int)($b['total_groups'] ?? 0), 'tg2' => (int)($b['total_groups'] ?? 0),
            'bpg' => (int)($b['beneficiaries_per_group'] ?? 10), 'bpg2' => (int)($b['beneficiaries_per_group'] ?? 10),
            'mpg' => (int)($b['meetings_per_group'] ?? 12), 'mpg2' => (int)($b['meetings_per_group'] ?? 12),
        ]);
        ok(null, 'Target saved');
    }
    fail('Method not allowed', 405);
}

// ─── Members ──────────────────────────────────────────────────────────────────
if (qp('resource') === 'members') {
    if ($method === 'GET') {
        $gid = qp('group_id');
        if (!$gid) fail('group_id required');
        $grp = $db->prepare('SELECT hospital_id FROM fbgdsd_groups WHERE id = :id');
        $grp->execute(['id' => $gid]);
        $grpHospital = $grp->fetchColumn();
        if ($grpHospital === false) fail('Group not found', 404);
        fbgdsdAssertHospitalInScope($user, $db, $grpHospital);
        $rows = $db->prepare("SELECT * FROM fbgdsd_members WHERE group_id = :g AND is_active = 1 ORDER BY surname, first_name");
        $rows->execute(['g' => $gid]);
        ok($rows->fetchAll());
    }
    if ($method === 'POST') {
        need($user, 'submissions.create');
        $b = body();
        fbgdsdAssertHospitalInScope($user, $db, $b['hospital_id'] ?? null);
        $mid = uid();
        $s = $db->prepare("INSERT INTO fbgdsd_members
            (id,group_id,hospital_id,first_name,surname,age,sex,educational_level,art_reg_number,drug_regimen,
             level_of_care,vl_suppressed,cd4_above_200,
             disclosure_done,disclosure_date,eac_eligible,eac_date_initiated,
             vl_date_taken,vl_result,ahd_date_done,ahd_result,
             tb_date_done,tb_result,tpt_eligible,tpt_provided,index_testing_done)
            VALUES
            (:id,:gid,:hid,:fn,:sn,:age,:sex,:edu,:art,:reg,
             :loc,:vls,:cd4,
             :disc,:ddate,:eac,:edate,
             :vdt,:vres,:ahd,:ares,
             :tbd,:tbres,:tpte,:tptp,:idx)
            ON DUPLICATE KEY UPDATE
            first_name=VALUES(first_name),surname=VALUES(surname),age=VALUES(age),drug_regimen=VALUES(drug_regimen),
            updated_at=NOW()");
        $s->execute([
            'id'   => $mid, 'gid' => $b['group_id'], 'hid' => $b['hospital_id'],
            'fn'   => $b['first_name'] ?? null, 'sn' => $b['surname'] ?? null,
            'age'  => isset($b['age']) ? (int)$b['age'] : null,
            'sex'  => $b['sex'] ?? null, 'edu' => $b['educational_level'] ?? null,
            'art'  => $b['art_reg_number'], 'reg' => $b['drug_regimen'] ?? null,
            'loc'  => $b['level_of_care'] ?? null,
            'vls'  => isset($b['vl_suppressed'])  ? (int)$b['vl_suppressed']  : null,
            'cd4'  => isset($b['cd4_above_200'])   ? (int)$b['cd4_above_200']  : null,
            'disc' => (int)($b['disclosure_done'] ?? 0),
            'ddate'=> $b['disclosure_date'] ?? null,
            'eac'  => (int)($b['eac_eligible'] ?? 0),
            'edate'=> $b['eac_date_initiated'] ?? null,
            'vdt'  => $b['vl_date_taken'] ?? null, 'vres' => $b['vl_result'] ?? null,
            'ahd'  => $b['ahd_date_done'] ?? null, 'ares' => $b['ahd_result'] ?? null,
            'tbd'  => $b['tb_date_done'] ?? null,  'tbres'=> $b['tb_result'] ?? null,
            'tpte' => (int)($b['tpt_eligible'] ?? 0),
            'tptp' => (int)($b['tpt_provided'] ?? 0),
            'idx'  => (int)($b['index_testing_done'] ?? 0),
        ]);
        ok(['id' => $mid], 'Member saved');
    }
    if ($method === 'DELETE' && $id) {
        need($user, 'submissions.edit');
        $db->prepare("UPDATE fbgdsd_members SET is_active=0 WHERE id=:id")->execute(['id' => $id]);
        ok(null, 'Member removed');
    }
    fail('Method not allowed', 405);
}

// ─── Meetings ─────────────────────────────────────────────────────────────────
if (qp('resource') === 'meetings') {
    if ($method === 'GET') {
        $gid = qp('group_id');
        if ($id) {
            // Single meeting + attendance
            $mtg = $db->prepare("SELECT m.*, g.group_name FROM fbgdsd_meetings m JOIN fbgdsd_groups g ON g.id=m.group_id WHERE m.id=:id");
            $mtg->execute(['id' => $id]);
            $meeting = $mtg->fetch();
            if (!$meeting) fail('Meeting not found', 404);
            fbgdsdAssertHospitalInScope($user, $db, $meeting['hospital_id']);
            $att = $db->prepare("SELECT a.*, mb.first_name, mb.surname, mb.art_reg_number FROM fbgdsd_attendance a JOIN fbgdsd_members mb ON mb.id=a.member_id WHERE a.meeting_id=:id");
            $att->execute(['id' => $id]);
            $meeting['attendance'] = $att->fetchAll();
            ok($meeting);
        }
        $scope  = fbgdsdScope($user);
        $scopeSql = str_replace('g.hospital_id', 'm.hospital_id', $scope['sql']);
        $where  = ['1=1', $scopeSql];
        $params = $scope['params'];
        if ($gid) { $where[] = 'm.group_id = :gid'; $params['gid'] = $gid; }
        $cond = implode(' AND ', $where);
        $rows = $db->prepare("SELECT m.*, g.group_name, COUNT(a.id) AS attendance_count
                              FROM fbgdsd_meetings m
                              JOIN fbgdsd_groups g ON g.id = m.group_id
                              LEFT JOIN hospitals h ON h.id = m.hospital_id
                              LEFT JOIN fbgdsd_attendance a ON a.meeting_id = m.id
                              WHERE $cond GROUP BY m.id ORDER BY m.meeting_date DESC LIMIT 100");
        $rows->execute($params);
        ok($rows->fetchAll());
    }
    if ($method === 'POST') {
        need($user, 'submissions.create');
        $b = body();
        fbgdsdAssertHospitalInScope($user, $db, $b['hospital_id'] ?? null);
        $mid = uid();
        $s = $db->prepare("INSERT INTO fbgdsd_meetings (id,group_id,hospital_id,meeting_date,topic,notes,created_by)
                           VALUES (:id,:gid,:hid,:dt,:topic,:notes,:by)");
        $s->execute([
            'id' => $mid, 'gid' => $b['group_id'], 'hid' => $b['hospital_id'],
            'dt' => $b['meeting_date'], 'topic' => $b['topic'] ?? null,
            'notes' => $b['notes'] ?? null, 'by' => $user['id'],
        ]);
        // Save attendance
        $att = $b['attendance'] ?? [];
        $ins = $db->prepare("INSERT IGNORE INTO fbgdsd_attendance (id,meeting_id,member_id,attended) VALUES (:id,:mid,:mbid,:att)");
        foreach ($att as $a) {
            $ins->execute(['id' => uid(), 'mid' => $mid, 'mbid' => $a['member_id'], 'att' => (int)($a['attended'] ?? 1)]);
        }
        ok(['id' => $mid], 'Meeting recorded');
    }
    fail('Method not allowed', 405);
}

// ─── Groups (default resource) ────────────────────────────────────────────────
if ($method === 'GET') {
    $scope = fbgdsdScope($user);

    if ($id) {
        $s = $db->prepare("SELECT g.*, h.name AS hospital_name,
                           COUNT(DISTINCT m.id)  AS meeting_count,
                           COUNT(DISTINCT mb.id) AS member_count
                           FROM fbgdsd_groups g
                           LEFT JOIN hospitals h   ON h.id  = g.hospital_id
                           LEFT JOIN fbgdsd_meetings m ON m.group_id = g.id
                           LEFT JOIN fbgdsd_members mb ON mb.group_id = g.id AND mb.is_active = 1
                           WHERE g.id = :id GROUP BY g.id");
        $s->execute(['id' => $id]);
        $group = $s->fetch();
        if (!$group) fail('Group not found', 404);
        if ($scope['sql'] !== '1=1') {
            fbgdsdAssertHospitalInScope($user, $db, $group['hospital_id']);
        }
        ok($group);
    }

    $page    = max(1, (int)qp('page', 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;
    $hid     = qp('hospital_id');
    $search  = qp('search');

    $where  = ['g.is_active = 1', $scope['sql']];
    $params = $scope['params'];
    if ($hid)    { $where[] = 'g.hospital_id = :hid'; $params['hid'] = $hid; }
    if ($search) { $where[] = '(g.group_name LIKE :s OR g.lead_nurse LIKE :s2)'; $params['s'] = "%$search%"; $params['s2'] = "%$search%"; }

    $cond = implode(' AND ', $where);
    $cnt = $db->prepare("SELECT COUNT(*) FROM fbgdsd_groups g LEFT JOIN hospitals h ON h.id = g.hospital_id WHERE $cond");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $q = $db->prepare("SELECT g.*, h.name AS hospital_name,
                       COUNT(DISTINCT m.id)  AS meeting_count,
                       COUNT(DISTINCT mb.id) AS member_count
                       FROM fbgdsd_groups g
                       LEFT JOIN hospitals h   ON h.id  = g.hospital_id
                       LEFT JOIN fbgdsd_meetings m ON m.group_id = g.id
                       LEFT JOIN fbgdsd_members mb ON mb.group_id = g.id AND mb.is_active = 1
                       WHERE $cond GROUP BY g.id ORDER BY g.created_at DESC LIMIT $perPage OFFSET $offset");
    $q->execute($params);
    paginate($q->fetchAll(), $total, $page, $perPage);
}

if ($method === 'POST') {
    need($user, 'submissions.create');
    $b = body();
    if (empty($b['group_name']) || empty($b['hospital_id'])) fail('group_name and hospital_id are required');
    fbgdsdAssertHospitalInScope($user, $db, $b['hospital_id']);
    $gid = uid();
    $s = $db->prepare("INSERT INTO fbgdsd_groups (id,hospital_id,group_name,lead_nurse,district,region,created_by)
                       VALUES (:id,:hid,:name,:nurse,:district,:region,:by)");
    $s->execute([
        'id' => $gid, 'hid' => $b['hospital_id'], 'name' => $b['group_name'],
        'nurse' => $b['lead_nurse'] ?? null, 'district' => $b['district'] ?? null,
        'region' => $b['region'] ?? null, 'by' => $user['id'],
    ]);
    ok(['id' => $gid], 'Group created');
}

if (($method === 'PUT' || $method === 'PATCH') && $id) {
    need($user, 'submissions.edit');
    $b = body();
    $s = $db->prepare("UPDATE fbgdsd_groups SET group_name=:name,lead_nurse=:nurse,district=:district,region=:region,
                       is_active=:active,updated_at=NOW() WHERE id=:id");
    $s->execute([
        'name' => $b['group_name'], 'nurse' => $b['lead_nurse'] ?? null,
        'district' => $b['district'] ?? null, 'region' => $b['region'] ?? null,
        'active' => (int)($b['is_active'] ?? 1), 'id' => $id,
    ]);
    ok(null, 'Group updated');
}

fail('Method not allowed', 405);
