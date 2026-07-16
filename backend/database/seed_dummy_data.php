<?php
/**
 * Dummy data seeder — populates hospitals, extra users, FBGDSD groups/members/
 * meetings, and FBG assessments with realistic, varied data so the dashboards
 * have something meaningful to show.
 *
 * Safe to re-run: hospitals/users use INSERT IGNORE, FBG assessments upsert
 * on (hospital_id, art_number, reporting_year, reporting_month).
 *
 * Usage (from the backend/ directory or anywhere — path is resolved below):
 *   php backend/database/seed_dummy_data.php
 *
 * Requires: mysql/001_schema.sql, mysql/002_seed.sql, fbgdsd_schema.sql,
 * fbg_schema.sql already loaded.
 */

declare(strict_types=1);

$root = dirname(__DIR__); // backend/
require_once $root . '/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function uid(): string {
    return sprintf('%08x-%04x-%04x-%04x-%012x',
        random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffff) | 0x4000,
        random_int(0, 0xffff) | 0x8000, random_int(0, 0xffffffffffff));
}
function pick(array $arr) { return $arr[array_rand($arr)]; }
function chance(float $pct): bool { return (mt_rand(0, 999) / 1000) < $pct; }

echo "Seeding dummy data into database " . DB_NAME . "...\n";

// ─── Regions (reuse if present, else create) ──────────────────────────────────
$regions = $pdo->query("SELECT id, name FROM regions ORDER BY name")->fetchAll();
if (empty($regions)) {
    $regionSeed = [['Northern Region', 'NORTH'], ['Southern Region', 'SOUTH']];
    foreach ($regionSeed as [$name, $code]) {
        $id = uid();
        $pdo->prepare("INSERT INTO regions (id, name, code) VALUES (:id, :name, :code)")
            ->execute(['id' => $id, 'name' => $name, 'code' => $code]);
        $regions[] = ['id' => $id, 'name' => $name];
    }
}
$regionIds = array_column($regions, 'id');

// ─── Hospitals ─────────────────────────────────────────────────────────────────
$hospitalSeed = [
    ['Central General Hospital', 'CGH', 'general', 3],
    ['Northern Medical Centre',  'NMC', 'general', 2],
    ['Southern District Hospital', 'SDH', 'district', 2],
    ['Eastern Health Clinic', 'EHC', 'clinic', 1],
    ['Western Regional Hospital', 'WRH', 'regional', 3],
];
$hospitals = [];
foreach ($hospitalSeed as [$name, $code, $type, $level]) {
    $existing = $pdo->prepare("SELECT id FROM hospitals WHERE code = :code");
    $existing->execute(['code' => $code]);
    $row = $existing->fetch();
    if ($row) { $hospitals[] = ['id' => $row['id'], 'name' => $name]; continue; }

    $id = uid();
    $pdo->prepare("INSERT INTO hospitals (id, region_id, name, code, type, level, is_active)
                   VALUES (:id, :rid, :name, :code, :type, :level, 1)")
        ->execute(['id' => $id, 'rid' => pick($regionIds), 'name' => $name, 'code' => $code, 'type' => $type, 'level' => $level]);
    $hospitals[] = ['id' => $id, 'name' => $name];
}
echo "Hospitals ready: " . count($hospitals) . "\n";

// ─── Extra users (hospital_admin / data_entry / regional_admin / viewer) ──────
$roles = $pdo->query("SELECT id, name FROM roles")->fetchAll();
$roleId = [];
foreach ($roles as $r) $roleId[$r['name']] = $r['id'];

$passwordHash = password_hash('Password@123', PASSWORD_DEFAULT);
$userSeed = [
    ['Regional Admin One', 'regional.admin@healthplatform.org', 'regional_admin', null],
    ['Hospital Admin CGH', 'admin.cgh@healthplatform.org', 'hospital_admin', $hospitals[0]['id']],
    ['Hospital Admin NMC', 'admin.nmc@healthplatform.org', 'hospital_admin', $hospitals[1]['id']],
    ['Data Entry CGH', 'data.cgh@healthplatform.org', 'data_entry', $hospitals[0]['id']],
    ['Data Entry NMC', 'data.nmc@healthplatform.org', 'data_entry', $hospitals[1]['id']],
    ['Data Entry SDH', 'data.sdh@healthplatform.org', 'data_entry', $hospitals[2]['id']],
    ['Viewer One', 'viewer@healthplatform.org', 'viewer', null],
];
foreach ($userSeed as [$name, $email, $roleName, $hospitalId]) {
    $existing = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $existing->execute(['email' => $email]);
    if ($existing->fetch()) continue;
    $pdo->prepare("INSERT INTO users (id, hospital_id, region_id, role_id, name, email, password_hash, is_active, email_verified_at)
                   VALUES (:id, :hid, :rid, :role, :name, :email, :pw, 1, NOW())")
        ->execute([
            'id' => uid(), 'hid' => $hospitalId, 'rid' => $hospitalId ? null : pick($regionIds),
            'role' => $roleId[$roleName], 'name' => $name, 'email' => $email, 'pw' => $passwordHash,
        ]);
}
echo "Extra users ready (password for all: Password@123)\n";

// ─── FBG Assessments ────────────────────────────────────────────────────────────
$now = new DateTime();
$reportMonth = (int)$now->format('n');
$reportYear  = (int)$now->format('Y');

$firstNames = ['Ama','Kofi','Adjoa','Kwesi','Efua','Yaw','Akosua','Kojo','Abena','Kwabena','Grace','John','Mary','David','Sarah','James','Linda','Michael','Patricia','Robert'];
$surnames   = ['Mensah','Owusu','Boateng','Asante','Osei','Appiah','Agyeman','Antwi','Darko','Frimpong','Johnson','Smith','Williams','Brown','Davis'];

$patientsPerHospital = 18;
$totalCreated = 0;
$artCounter = 1000;

foreach ($hospitals as $h) {
    for ($p = 0; $p < $patientsPerHospital; $p++) {
        $artNumber = 'ART' . str_pad((string)($artCounter++), 6, '0', STR_PAD_LEFT);
        $gender = pick(['M', 'F']);
        $age = random_int(4, 68);

        // Progressive follow-up: baseline always present; each later period has a
        // decreasing chance of existing (attrition), and suppression improves over time.
        $periods = [
            'baseline' => 1.0,
            'vl6'      => 0.85,
            'vl12'     => 0.7,
            'vl18'     => 0.55,
            'vl24'     => 0.4,
        ];
        $suppressionChance = ['baseline' => 0.55, 'vl6' => 0.72, 'vl12' => 0.83, 'vl18' => 0.9, 'vl24' => 0.93];

        $row = [
            'hospital_id' => $h['id'], 'reporting_month' => $reportMonth, 'reporting_year' => $reportYear,
            'art_number' => $artNumber, 'gender' => $gender, 'age' => $age,
        ];
        $latestSuppressed = null;
        foreach ($periods as $key => $presenceChance) {
            if (chance($presenceChance)) {
                $suppressed = chance($suppressionChance[$key]);
                $latestSuppressed = $suppressed;
                $vl = $suppressed ? (string)random_int(0, 199) : (string)random_int(1000, 12000);
                $row["{$key}_viral_load"] = $vl;
                $row["{$key}_status"] = $suppressed ? 'suppressed' : 'unsuppressed';
            } else {
                $row["{$key}_viral_load"] = null;
                $row["{$key}_status"] = null;
            }
        }

        $cd4Above = $latestSuppressed === null ? chance(0.7) : ($latestSuppressed ? chance(0.85) : chance(0.35));
        $tbPositive = chance(0.08);
        $row['screened_for_ahd']  = chance(0.9) ? 1 : 0;
        $row['cd4_above_200']     = $cd4Above ? 1 : 0;
        $row['cd4_below_200']     = $cd4Above ? 0 : 1;
        $row['screened_for_tb']   = chance(0.92) ? 1 : 0;
        $row['negative_for_tb']   = $row['screened_for_tb'] && !$tbPositive ? 1 : 0;
        $row['positive_for_tb']   = $row['screened_for_tb'] && $tbPositive ? 1 : 0;
        $row['eligible_tpt_previous'] = chance(0.3) ? 1 : 0;
        $row['receiving_tpt']     = $row['eligible_tpt_previous'] && chance(0.75) ? 1 : 0;
        $row['disclosure_done']   = chance(0.8) ? 1 : 0;
        $row['index_testing_done'] = chance(0.65) ? 1 : 0;
        $row['number_tested']     = $row['index_testing_done'] ? random_int(0, 4) : 0;
        $row['prepared_by_name']  = pick($firstNames) . ' ' . pick($surnames);
        $row['prepared_by_designation'] = pick(['Nurse', 'Clinical Officer', 'Physician Assistant', 'Counsellor']);
        $row['prepared_by_signature']   = $row['prepared_by_name'];

        $sql = "INSERT INTO fbg_assessments
            (id,hospital_id,reporting_month,reporting_year,art_number,gender,age,
             baseline_viral_load,baseline_status,vl6_viral_load,vl6_status,
             vl12_viral_load,vl12_status,vl18_viral_load,vl18_status,vl24_viral_load,vl24_status,
             screened_for_ahd,cd4_above_200,cd4_below_200,
             screened_for_tb,negative_for_tb,positive_for_tb,eligible_tpt_previous,receiving_tpt,
             disclosure_done,index_testing_done,number_tested,
             prepared_by_name,prepared_by_designation,prepared_by_signature)
            VALUES
            (:id,:hospital_id,:reporting_month,:reporting_year,:art_number,:gender,:age,
             :baseline_viral_load,:baseline_status,:vl6_viral_load,:vl6_status,
             :vl12_viral_load,:vl12_status,:vl18_viral_load,:vl18_status,:vl24_viral_load,:vl24_status,
             :screened_for_ahd,:cd4_above_200,:cd4_below_200,
             :screened_for_tb,:negative_for_tb,:positive_for_tb,:eligible_tpt_previous,:receiving_tpt,
             :disclosure_done,:index_testing_done,:number_tested,
             :prepared_by_name,:prepared_by_designation,:prepared_by_signature)
            ON DUPLICATE KEY UPDATE gender=VALUES(gender)";
        $row['id'] = uid();
        $pdo->prepare($sql)->execute($row);
        $totalCreated++;
    }
}
echo "FBG assessments created: $totalCreated\n";

// ─── FBGDSD groups / members / meetings ────────────────────────────────────────
$topics = ['Disclosure Support','Psychosocial Support','Adherence Counselling & Support',
    'HIV Health Education & Treatment Literacy','ART Defaulter Counselling','U=U Concept'];

$groupsCreated = 0;
foreach (array_slice($hospitals, 0, 3) as $h) {
    $groupId = uid();
    $pdo->prepare("INSERT INTO fbgdsd_groups (id, hospital_id, group_name, lead_nurse, district, region)
                   VALUES (:id, :hid, :name, :nurse, :district, :region)")
        ->execute([
            'id' => $groupId, 'hid' => $h['id'], 'name' => 'Group A — ' . $h['name'],
            'nurse' => pick($firstNames) . ' ' . pick($surnames), 'district' => 'Central District', 'region' => 'Region A',
        ]);
    $groupsCreated++;

    $memberIds = [];
    for ($m = 0; $m < random_int(6, 10); $m++) {
        $memberId = uid();
        $vlSup = chance(0.75);
        $cd4Above = chance(0.7);
        $pdo->prepare("INSERT INTO fbgdsd_members
            (id, group_id, hospital_id, first_name, surname, age, sex, educational_level, art_reg_number,
             drug_regimen, level_of_care, vl_suppressed, cd4_above_200, disclosure_done, disclosure_date,
             eac_eligible, tpt_eligible, tpt_provided, index_testing_done)
            VALUES (:id, :gid, :hid, :fn, :sn, :age, :sex, :edu, :art, :reg, :loc, :vls, :cd4, :disc, :ddate,
             :eac, :tpte, :tptp, :idx)")
            ->execute([
                'id' => $memberId, 'gid' => $groupId, 'hid' => $h['id'],
                'fn' => pick($firstNames), 'sn' => pick($surnames), 'age' => random_int(18, 60),
                'sex' => pick(['M', 'F']), 'edu' => pick(['Primary', 'Secondary', 'Tertiary']),
                'art' => 'DSD' . str_pad((string)($artCounter++), 6, '0', STR_PAD_LEFT),
                'reg' => pick(['first_line', 'second_line']), 'loc' => pick(['established', 'non_established']),
                'vls' => $vlSup ? 1 : 0, 'cd4' => $cd4Above ? 1 : 0,
                'disc' => chance(0.8) ? 1 : 0, 'ddate' => chance(0.8) ? $now->format('Y-m-d') : null,
                'eac' => chance(0.2) ? 1 : 0, 'tpte' => chance(0.3) ? 1 : 0, 'tptp' => chance(0.2) ? 1 : 0,
                'idx' => chance(0.6) ? 1 : 0,
            ]);
        $memberIds[] = $memberId;
    }

    for ($mt = 0; $mt < 2; $mt++) {
        $meetingId = uid();
        $meetingDate = (clone $now)->modify('-' . ($mt * 30 + random_int(1, 10)) . ' days')->format('Y-m-d');
        $pdo->prepare("INSERT INTO fbgdsd_meetings (id, group_id, hospital_id, meeting_date, topic)
                       VALUES (:id, :gid, :hid, :dt, :topic)")
            ->execute(['id' => $meetingId, 'gid' => $groupId, 'hid' => $h['id'], 'dt' => $meetingDate, 'topic' => pick($topics)]);
        foreach ($memberIds as $mid) {
            if (chance(0.85)) {
                $pdo->prepare("INSERT IGNORE INTO fbgdsd_attendance (id, meeting_id, member_id, attended) VALUES (:id, :mid, :mbid, 1)")
                    ->execute(['id' => uid(), 'mid' => $meetingId, 'mbid' => $mid]);
            }
        }
    }
}
echo "FBGDSD groups created: $groupsCreated (with members and meetings)\n";

echo "\nDone. Log in as any seeded user (password: Password@123) or the super admin to explore.\n";
