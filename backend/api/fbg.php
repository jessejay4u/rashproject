<?php
require_once __DIR__ . '/bootstrap.php';
$user   = auth();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = qp('id');

// ─── Scope: data_entry / hospital_admin restricted to their own hospital ──────
function fbgScope(array $user): array {
    if (in_array($user['role'], ['data_entry', 'hospital_admin'], true)) {
        return ['sql' => 'f.hospital_id = :scope_hid', 'params' => ['scope_hid' => $user['hospital_id']]];
    }
    if ($user['role'] === 'regional_admin') {
        return ['sql' => 'h.region_id = :scope_rid', 'params' => ['scope_rid' => $user['region_id']]];
    }
    return ['sql' => '1=1', 'params' => []];
}

function fbgStatusFromRow(array $r): ?string {
    foreach (['vl24_status', 'vl18_status', 'vl12_status', 'vl6_status', 'baseline_status'] as $k) {
        if (!empty($r[$k])) return $r[$k];
    }
    return null;
}

function fbgLatestViralLoad(array $r): ?string {
    foreach (['vl24_viral_load', 'vl18_viral_load', 'vl12_viral_load', 'vl6_viral_load', 'baseline_viral_load'] as $k) {
        if ($r[$k] !== null && $r[$k] !== '') return $r[$k];
    }
    return null;
}

// data_entry/hospital_admin are pinned to their own hospital; regional_admin must stay within their region.
// Returns the hospital_id to actually use, or fails the request with 403 if the requested hospital is out of scope.
function fbgAssertHospitalInScope(PDO $db, array $user, ?string $hospitalId): string {
    if (in_array($user['role'], ['data_entry', 'hospital_admin'], true)) {
        return $user['hospital_id'];
    }
    if ($user['role'] === 'regional_admin') {
        if (!$hospitalId) fail('hospital_id is required');
        $s = $db->prepare('SELECT region_id FROM hospitals WHERE id = :id');
        $s->execute(['id' => $hospitalId]);
        $region = $s->fetchColumn();
        if ($region === false || $region !== $user['region_id']) fail('Access denied for this facility', 403);
        return $hospitalId;
    }
    if (!$hospitalId) fail('hospital_id is required');
    return $hospitalId;
}

// ─── Dashboard aggregation ──────────────────────────────────────────────────
if (qp('resource') === 'dashboard') {
    $scope = fbgScope($user);
    $hid = qp('hospital_id');

    // Latest submission per patient (most recent reporting period), scoped by role/hospital
    $innerScopeSql = str_replace('h.region_id', 'hh.region_id', $scope['sql']);
    $innerScopeSql = str_replace('f.hospital_id', 'ff.hospital_id', $innerScopeSql);
    $latestSql = "
        SELECT f.* FROM fbg_assessments f
        INNER JOIN (
            SELECT ff.hospital_id, ff.art_number,
                   MAX(ff.reporting_year * 100 + ff.reporting_month) AS maxperiod
            FROM fbg_assessments ff
            JOIN hospitals hh ON hh.id = ff.hospital_id
            WHERE ff.is_active = 1 AND $innerScopeSql" . ($hid ? ' AND ff.hospital_id = :fhid' : '') . "
            GROUP BY ff.hospital_id, ff.art_number
        ) latest ON latest.hospital_id = f.hospital_id AND latest.art_number = f.art_number
                 AND (f.reporting_year * 100 + f.reporting_month) = latest.maxperiod
        WHERE f.is_active = 1
    ";
    $stmt = $db->prepare($latestSql);
    $stmt->execute(array_merge($scope['params'], $hid ? ['fhid' => $hid] : []));
    $latest = $stmt->fetchAll();

    $total = count($latest);
    $suppressed = $unsuppressed = 0;
    $cd4Above = $cd4Below = 0;
    $tbNeg = $tbPos = $tpt = 0;
    $genderM = $genderF = 0;
    $ageBuckets = ['0-14' => 0, '15-24' => 0, '25-34' => 0, '35-44' => 0, '45+' => 0];
    $discDone = $indexDone = 0; $peopleTested = 0;
    $dueByPeriod = ['Baseline' => 0, '6 Months' => 0, '12 Months' => 0, '18 Months' => 0, '24 Months' => 0];
    $trendCounts = [
        'Baseline'   => ['s' => 0, 't' => 0],
        '6 Months'   => ['s' => 0, 't' => 0],
        '12 Months'  => ['s' => 0, 't' => 0],
        '18 Months'  => ['s' => 0, 't' => 0],
        '24 Months'  => ['s' => 0, 't' => 0],
    ];
    $attention = [];

    foreach ($latest as $r) {
        $status = fbgStatusFromRow($r);
        if ($status === 'suppressed') $suppressed++;
        elseif ($status === 'unsuppressed') $unsuppressed++;

        if (!empty($r['cd4_above_200'])) $cd4Above++;
        if (!empty($r['cd4_below_200'])) $cd4Below++;
        if (!empty($r['negative_for_tb'])) $tbNeg++;
        if (!empty($r['positive_for_tb'])) $tbPos++;
        if (!empty($r['receiving_tpt'])) $tpt++;

        if (($r['gender'] ?? '') === 'M') $genderM++;
        elseif (($r['gender'] ?? '') === 'F') $genderF++;

        $age = $r['age'] !== null ? (int)$r['age'] : null;
        if ($age !== null) {
            if ($age <= 14) $ageBuckets['0-14']++;
            elseif ($age <= 24) $ageBuckets['15-24']++;
            elseif ($age <= 34) $ageBuckets['25-34']++;
            elseif ($age <= 44) $ageBuckets['35-44']++;
            else $ageBuckets['45+']++;
        }

        if (!empty($r['disclosure_done'])) $discDone++;
        if (!empty($r['index_testing_done'])) $indexDone++;
        $peopleTested += (int)($r['number_tested'] ?? 0);

        // Trend: suppression rate at each period (only rows that have that period recorded)
        $periods = [
            'Baseline'  => $r['baseline_status'] ?? null,
            '6 Months'  => $r['vl6_status'] ?? null,
            '12 Months' => $r['vl12_status'] ?? null,
            '18 Months' => $r['vl18_status'] ?? null,
            '24 Months' => $r['vl24_status'] ?? null,
        ];
        foreach ($periods as $label => $val) {
            if (!empty($val)) {
                $trendCounts[$label]['t']++;
                if ($val === 'suppressed') $trendCounts[$label]['s']++;
            }
        }

        // Due for next viral load: has this period but not the next
        if (!empty($r['baseline_status']) && empty($r['vl6_status']))  $dueByPeriod['6 Months']++;
        if (!empty($r['vl6_status'])       && empty($r['vl12_status'])) $dueByPeriod['12 Months']++;
        if (!empty($r['vl12_status'])      && empty($r['vl18_status'])) $dueByPeriod['18 Months']++;
        if (!empty($r['vl18_status'])      && empty($r['vl24_status'])) $dueByPeriod['24 Months']++;
        if (empty($r['baseline_status'])) $dueByPeriod['Baseline']++;

        if ($status === 'unsuppressed' || !empty($r['positive_for_tb']) || !empty($r['cd4_below_200'])) {
            $attention[] = [
                'art_number' => $r['art_number'],
                'viral_load' => fbgLatestViralLoad($r),
                'status'     => $status,
                'tb'         => !empty($r['positive_for_tb']) ? 'Positive' : (!empty($r['negative_for_tb']) ? 'Negative' : '—'),
                'cd4'        => !empty($r['cd4_below_200']) ? 'Below 200' : (!empty($r['cd4_above_200']) ? 'Above 200' : '—'),
                'follow_up_due' => (empty($r['vl6_status']) || empty($r['vl12_status']) || empty($r['vl18_status']) || empty($r['vl24_status'])),
            ];
        }
    }
    usort($attention, fn($a, $b) => ($b['status'] === 'unsuppressed') <=> ($a['status'] === 'unsuppressed'));
    $attention = array_slice($attention, 0, 20);

    $trend = [];
    foreach ($trendCounts as $label => $c) {
        $trend[] = ['period' => $label, 'suppression_pct' => $c['t'] > 0 ? round($c['s'] / $c['t'] * 100, 1) : 0];
    }

    // Facilities count (scoped) — reuses the distinct hospital_ids already present in $latest,
    // falling back to a direct hospitals-table scope for roles with no assessments yet.
    if (in_array($user['role'], ['data_entry', 'hospital_admin'], true)) {
        $facWhere = 'id = :scope_hid';
    } elseif ($user['role'] === 'regional_admin') {
        $facWhere = 'region_id = :scope_rid';
    } else {
        $facWhere = '1=1';
    }
    $facStmt = $db->prepare("SELECT COUNT(*) FROM hospitals WHERE is_active = 1 AND $facWhere" . ($hid ? ' AND id = :fhid' : ''));
    $facStmt->execute(array_merge($scope['params'], $hid ? ['fhid' => $hid] : []));
    $facilityCount = (int)$facStmt->fetchColumn();

    // Monthly performance by facility (computed in PHP from the already-fetched latest rows)
    $byFacility = [];
    foreach ($latest as $r) {
        $hidKey = $r['hospital_id'];
        if (!isset($byFacility[$hidKey])) $byFacility[$hidKey] = ['hospital_id' => $hidKey, 'total' => 0, 'suppressed' => 0];
        $byFacility[$hidKey]['total']++;
        if (fbgStatusFromRow($r) === 'suppressed') $byFacility[$hidKey]['suppressed']++;
    }
    if (!empty($byFacility)) {
        $ids = array_keys($byFacility);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $hq  = $db->prepare("SELECT id, name FROM hospitals WHERE id IN ($in)");
        $hq->execute($ids);
        $names = [];
        foreach ($hq->fetchAll() as $h) $names[$h['id']] = $h['name'];
        foreach ($byFacility as &$row) {
            $row['hospital_name'] = $names[$row['hospital_id']] ?? 'Unknown';
            $row['suppression_pct'] = $row['total'] > 0 ? round($row['suppressed'] / $row['total'] * 100, 1) : 0;
        }
        unset($row);
    }
    $byFacility = array_values($byFacility);
    usort($byFacility, fn($a, $b) => $b['total'] <=> $a['total']);

    ok([
        'stats' => [
            'total_patients'   => $total,
            'suppressed'       => $suppressed,
            'unsuppressed'     => $unsuppressed,
            'suppression_pct'  => $total > 0 ? round($suppressed / $total * 100, 1) : 0,
            'due_followup'     => array_sum($dueByPeriod) - $dueByPeriod['Baseline'],
            'facilities'       => $facilityCount,
        ],
        'trend'            => $trend,
        'viral_status'     => ['suppressed' => $suppressed, 'unsuppressed' => $unsuppressed],
        'cd4'              => ['above_200' => $cd4Above, 'below_200' => $cd4Below],
        'tb'               => ['negative' => $tbNeg, 'positive' => $tbPos, 'receiving_tpt' => $tpt],
        'gender'           => ['male' => $genderM, 'female' => $genderF],
        'age'              => $ageBuckets,
        'disclosure'       => [
            'disclosure_done_pct'    => $total > 0 ? round($discDone / $total * 100, 1) : 0,
            'index_testing_done_pct' => $total > 0 ? round($indexDone / $total * 100, 1) : 0,
            'people_tested'          => $peopleTested,
        ],
        'due_by_period'    => $dueByPeriod,
        'attention'        => $attention,
        'by_facility'      => array_slice($byFacility, 0, 15),
    ]);
}

// ─── List / Get / Create / Update / Delete ─────────────────────────────────
if ($method === 'GET') {
    $scope = fbgScope($user);

    if ($id) {
        $s = $db->prepare("SELECT f.*, h.name AS hospital_name FROM fbg_assessments f
                           JOIN hospitals h ON h.id = f.hospital_id
                           WHERE f.id = :id AND f.is_active = 1 AND {$scope['sql']}");
        $s->execute(array_merge(['id' => $id], $scope['params']));
        $row = $s->fetch();
        if (!$row) fail('Assessment not found', 404);
        ok($row);
    }

    $page    = max(1, (int)qp('page', 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where  = ['f.is_active = 1', $scope['sql']];
    $params = $scope['params'];
    if ($hid = qp('hospital_id'))     { $where[] = 'f.hospital_id = :hid';        $params['hid'] = $hid; }
    if ($m = qp('month'))             { $where[] = 'f.reporting_month = :m';      $params['m'] = (int)$m; }
    if ($y = qp('year'))              { $where[] = 'f.reporting_year = :y';       $params['y'] = (int)$y; }
    if ($search = qp('search'))       { $where[] = 'f.art_number LIKE :search';   $params['search'] = "%$search%"; }

    $cond = implode(' AND ', $where);

    $cnt = $db->prepare("SELECT COUNT(*) FROM fbg_assessments f JOIN hospitals h ON h.id = f.hospital_id WHERE $cond");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $q = $db->prepare("SELECT f.*, h.name AS hospital_name FROM fbg_assessments f
                       JOIN hospitals h ON h.id = f.hospital_id
                       WHERE $cond
                       ORDER BY f.reporting_year DESC, f.reporting_month DESC, f.updated_at DESC
                       LIMIT $perPage OFFSET $offset");
    $q->execute($params);
    paginate($q->fetchAll(), $total, $page, $perPage);
}

function fbgUpsertParams(array $b, ?string $userId): array {
    return [
        'hid'   => $b['hospital_id'] ?? null,
        'rm'    => (int)($b['reporting_month'] ?? 0),
        'ry'    => (int)($b['reporting_year'] ?? 0),
        'art'   => $b['art_number'] ?? '',
        'gender'=> $b['gender'] ?? null,
        'age'   => isset($b['age']) && $b['age'] !== '' ? (int)$b['age'] : null,

        'b_vl'  => $b['baseline_viral_load'] ?? null, 'b_st'  => $b['baseline_status'] ?? null,
        'v6_vl' => $b['vl6_viral_load'] ?? null,       'v6_st' => $b['vl6_status'] ?? null,
        'v12_vl'=> $b['vl12_viral_load'] ?? null,      'v12_st'=> $b['vl12_status'] ?? null,
        'v18_vl'=> $b['vl18_viral_load'] ?? null,      'v18_st'=> $b['vl18_status'] ?? null,
        'v24_vl'=> $b['vl24_viral_load'] ?? null,      'v24_st'=> $b['vl24_status'] ?? null,

        'ahd'   => (int)($b['screened_for_ahd'] ?? 0),
        'cd4a'  => (int)($b['cd4_above_200'] ?? 0),
        'cd4b'  => (int)($b['cd4_below_200'] ?? 0),

        'tbs'   => (int)($b['screened_for_tb'] ?? 0),
        'tbn'   => (int)($b['negative_for_tb'] ?? 0),
        'tbp'   => (int)($b['positive_for_tb'] ?? 0),
        'tpte'  => (int)($b['eligible_tpt_previous'] ?? 0),
        'tptp'  => (int)($b['receiving_tpt'] ?? 0),

        'disc'  => (int)($b['disclosure_done'] ?? 0),
        'idx'   => (int)($b['index_testing_done'] ?? 0),
        'ntest' => (int)($b['number_tested'] ?? 0),

        'pname' => $b['prepared_by_name'] ?? null,
        'pdesig'=> $b['prepared_by_designation'] ?? null,
        'psign' => $b['prepared_by_signature'] ?? null,
        'by'    => $userId,
    ];
}

if ($method === 'POST') {
    need($user, 'submissions.create');
    $b = body();
    if (empty($b['art_number']) || empty($b['reporting_month']) || empty($b['reporting_year'])) {
        fail('hospital_id, art_number, reporting_month and reporting_year are required');
    }
    $b['hospital_id'] = fbgAssertHospitalInScope($db, $user, $b['hospital_id'] ?? null);
    $fid = uid();
    $p   = fbgUpsertParams($b, $user['id']);
    $p['id'] = $fid;

    $s = $db->prepare("INSERT INTO fbg_assessments
        (id,hospital_id,reporting_month,reporting_year,art_number,gender,age,
         baseline_viral_load,baseline_status,vl6_viral_load,vl6_status,
         vl12_viral_load,vl12_status,vl18_viral_load,vl18_status,vl24_viral_load,vl24_status,
         screened_for_ahd,cd4_above_200,cd4_below_200,
         screened_for_tb,negative_for_tb,positive_for_tb,eligible_tpt_previous,receiving_tpt,
         disclosure_done,index_testing_done,number_tested,
         prepared_by_name,prepared_by_designation,prepared_by_signature,created_by)
        VALUES
        (:id,:hid,:rm,:ry,:art,:gender,:age,
         :b_vl,:b_st,:v6_vl,:v6_st,
         :v12_vl,:v12_st,:v18_vl,:v18_st,:v24_vl,:v24_st,
         :ahd,:cd4a,:cd4b,
         :tbs,:tbn,:tbp,:tpte,:tptp,
         :disc,:idx,:ntest,
         :pname,:pdesig,:psign,:by)
        ON DUPLICATE KEY UPDATE
         gender=VALUES(gender), age=VALUES(age),
         baseline_viral_load=VALUES(baseline_viral_load), baseline_status=VALUES(baseline_status),
         vl6_viral_load=VALUES(vl6_viral_load), vl6_status=VALUES(vl6_status),
         vl12_viral_load=VALUES(vl12_viral_load), vl12_status=VALUES(vl12_status),
         vl18_viral_load=VALUES(vl18_viral_load), vl18_status=VALUES(vl18_status),
         vl24_viral_load=VALUES(vl24_viral_load), vl24_status=VALUES(vl24_status),
         screened_for_ahd=VALUES(screened_for_ahd), cd4_above_200=VALUES(cd4_above_200), cd4_below_200=VALUES(cd4_below_200),
         screened_for_tb=VALUES(screened_for_tb), negative_for_tb=VALUES(negative_for_tb),
         positive_for_tb=VALUES(positive_for_tb), eligible_tpt_previous=VALUES(eligible_tpt_previous), receiving_tpt=VALUES(receiving_tpt),
         disclosure_done=VALUES(disclosure_done), index_testing_done=VALUES(index_testing_done), number_tested=VALUES(number_tested),
         prepared_by_name=VALUES(prepared_by_name), prepared_by_designation=VALUES(prepared_by_designation),
         prepared_by_signature=VALUES(prepared_by_signature), updated_at=NOW()");
    $s->execute($p);
    ok(['id' => $fid], 'Assessment saved');
}

if (($method === 'PUT' || $method === 'PATCH') && $id) {
    need($user, 'submissions.edit');
    $scope = fbgScope($user);
    $exists = $db->prepare("SELECT f.id FROM fbg_assessments f JOIN hospitals h ON h.id = f.hospital_id
                            WHERE f.id = :id AND f.is_active = 1 AND {$scope['sql']}");
    $exists->execute(array_merge(['id' => $id], $scope['params']));
    if (!$exists->fetch()) fail('Assessment not found', 404);

    $b = body();
    $b['hospital_id'] = fbgAssertHospitalInScope($db, $user, $b['hospital_id'] ?? null);
    $p = fbgUpsertParams($b, $user['id']);
    unset($p['by']); // created_by is not modified on edit
    $p['id'] = $id;

    $s = $db->prepare("UPDATE fbg_assessments SET
        hospital_id=:hid, reporting_month=:rm, reporting_year=:ry, art_number=:art, gender=:gender, age=:age,
        baseline_viral_load=:b_vl, baseline_status=:b_st,
        vl6_viral_load=:v6_vl, vl6_status=:v6_st,
        vl12_viral_load=:v12_vl, vl12_status=:v12_st,
        vl18_viral_load=:v18_vl, vl18_status=:v18_st,
        vl24_viral_load=:v24_vl, vl24_status=:v24_st,
        screened_for_ahd=:ahd, cd4_above_200=:cd4a, cd4_below_200=:cd4b,
        screened_for_tb=:tbs, negative_for_tb=:tbn, positive_for_tb=:tbp,
        eligible_tpt_previous=:tpte, receiving_tpt=:tptp,
        disclosure_done=:disc, index_testing_done=:idx, number_tested=:ntest,
        prepared_by_name=:pname, prepared_by_designation=:pdesig, prepared_by_signature=:psign,
        updated_at=NOW()
        WHERE id=:id");
    $s->execute($p);
    ok(null, 'Assessment updated');
}

if ($method === 'DELETE' && $id) {
    need($user, 'submissions.edit');
    $scope = fbgScope($user);
    $exists = $db->prepare("SELECT f.id FROM fbg_assessments f JOIN hospitals h ON h.id = f.hospital_id
                            WHERE f.id = :id AND f.is_active = 1 AND {$scope['sql']}");
    $exists->execute(array_merge(['id' => $id], $scope['params']));
    if (!$exists->fetch()) fail('Assessment not found', 404);

    $db->prepare("UPDATE fbg_assessments SET is_active=0 WHERE id=:id")->execute(['id' => $id]);
    ok(null, 'Assessment removed');
}

fail('Method not allowed', 405);
