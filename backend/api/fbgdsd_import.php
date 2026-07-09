<?php
/**
 * FBGDSD Excel Import
 * Accepts a .xlsx file upload, parses the FBGDSD Register sheet,
 * and inserts groups + members into the database.
 * Uses PHP's built-in ZipArchive + SimpleXML — NO Composer required.
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST only', 405);
$user = auth();
need($user, 'create_submissions');

$hospitalId = $_POST['hospital_id'] ?? null;
if (!$hospitalId) fail('hospital_id is required');

if (empty($_FILES['file']['tmp_name'])) fail('No file uploaded');
$file = $_FILES['file']['tmp_name'];
$ext  = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') fail('Only .xlsx files are supported');

// ─── Pure-PHP xlsx reader ─────────────────────────────────────────────────────
function read_xlsx(string $path): array {
    $z = new ZipArchive();
    if ($z->open($path) !== true) throw new Exception('Cannot open xlsx file');

    // Shared strings
    $strings = [];
    if ($xml = $z->getFromName('xl/sharedStrings.xml')) {
        $ss = new SimpleXMLElement($xml);
        foreach ($ss->si as $si) {
            $t = '';
            foreach ($si->xpath('.//t') as $node) $t .= (string)$node;
            $strings[] = trim($t);
        }
    }

    // Sheet relationship map
    $sheetMap = [];
    if ($relsXml = $z->getFromName('xl/_rels/workbook.xml.rels')) {
        $rels = new SimpleXMLElement($relsXml);
        foreach ($rels->Relationship as $rel) {
            $sheetMap[(string)$rel['Id']] = 'xl/' . (string)$rel['Target'];
        }
    }

    // Workbook sheet list
    $sheets = [];
    if ($wbXml = $z->getFromName('xl/workbook.xml')) {
        $wb = new SimpleXMLElement($wbXml);
        $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        foreach ($wb->sheets->sheet as $sheet) {
            $rid  = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $name = (string)$sheet['name'];
            $path = $sheetMap[$rid] ?? null;
            if (!$path) continue;
            $wsXml = $z->getFromName($path);
            if (!$wsXml) continue;

            $ws   = new SimpleXMLElement($wsXml);
            $ns   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $ws->registerXPathNamespace('x', $ns);
            $rows = [];
            foreach ($ws->xpath('//x:row') as $row) {
                $maxCol = 0;
                $cells  = [];
                foreach ($row->xpath('x:c') as $cell) {
                    $ref   = (string)$cell['r'];
                    $col   = col_index($ref);
                    $type  = (string)$cell['t'];
                    $v     = (string)$cell->v;
                    if ($type === 's') $v = $strings[(int)$v] ?? '';
                    elseif ($type === 'b') $v = $v ? 'TRUE' : 'FALSE';
                    $cells[$col] = $v;
                    if ($col > $maxCol) $maxCol = $col;
                }
                $rowArr = [];
                for ($c = 0; $c <= $maxCol; $c++) $rowArr[] = $cells[$c] ?? '';
                $rows[] = $rowArr;
            }
            $sheets[$name] = $rows;
        }
    }
    $z->close();
    return $sheets;
}

// Convert cell ref (A1, BC4) to 0-based column index
function col_index(string $ref): int {
    preg_match('/([A-Z]+)/', $ref, $m);
    $col = 0;
    foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - ord('A') + 1);
    return $col - 1;
}

function cell(array $row, int $i): string {
    return isset($row[$i]) ? trim((string)$row[$i]) : '';
}

function ymd(string $val): ?string {
    if (!$val) return null;
    // Excel date serial or date string
    if (is_numeric($val)) {
        $ts = ($val - 25569) * 86400;
        return date('Y-m-d', (int)$ts);
    }
    try { return (new DateTime($val))->format('Y-m-d'); } catch (Exception $e) { return null; }
}

// ─── Parse and import ─────────────────────────────────────────────────────────
try {
    $sheets = read_xlsx($file);
} catch (Exception $e) {
    fail('Failed to read file: ' . $e->message);
}

$register = $sheets['FBGDSD REGISTER'] ?? null;
if (!$register) fail('Sheet "FBGDSD REGISTER" not found in this file');

$db = db();

// Extract meeting header (rows 3–8 are label rows)
$meetingDate = '';
$leadNurse   = '';
$topic       = '';
$district    = '';
$facility    = '';
$region      = '';

foreach ($register as $i => $row) {
    $label = strtolower(cell($row, 0));
    $val   = cell($row, 1) ?: cell($row, 2); // value is typically in col B or C
    if (str_contains($label, 'date'))     $meetingDate = ymd($val) ?: date('Y-m-d');
    if (str_contains($label, 'nurse'))    $leadNurse   = $val;
    if (str_contains($label, 'topic'))    $topic       = $val;
    if (str_contains($label, 'district')) $district    = $val;
    if (str_contains($label, 'facility')) $facility    = $val;
    if (str_contains($label, 'region'))   $region      = $val;
}

// Find the data rows (after the header row that contains "ART Registration Number")
$dataStart = null;
foreach ($register as $i => $row) {
    $joined = implode(' ', $row);
    if (str_contains(strtolower($joined), 'art registration')) {
        $dataStart = $i + 2; // skip column sub-header row
        break;
    }
}
if ($dataStart === null) fail('Could not find patient data rows in the register');

// Column positions from Excel sheet (0-indexed):
// 0=#, 1=First Name, 2=Surname, 3=Age, 4-5=Sex(M/F), 6=Edu, 7=ART Reg#, 8-9=Regimen
// 10=established, 11=non-established, 12=VL non-suppressed, 13=VL suppressed
// 14=CD4>200, 15=CD4<200, 16=disclosure done, 17=disclosure date
// 18=EAC eligible, 19=EAC date, 20=VL date, 21=VL result
// 22=AHD date, 23=AHD result, 24=TB date, 25=TB result
// 26=TPT eligible, 27=TPT provided

// Get or create group
$groupName = $facility ?: 'Imported Group';
$grpCheck = $db->prepare("SELECT id FROM fbgdsd_groups WHERE group_name=:n AND hospital_id=:h LIMIT 1");
$grpCheck->execute(['n' => $groupName, 'h' => $hospitalId]);
$existingGroup = $grpCheck->fetch();

if ($existingGroup) {
    $groupId = $existingGroup['id'];
    $db->prepare("UPDATE fbgdsd_groups SET lead_nurse=:nurse,district=:district,region=:region,updated_at=NOW() WHERE id=:id")
       ->execute(['nurse' => $leadNurse, 'district' => $district, 'region' => $region, 'id' => $groupId]);
} else {
    $groupId = uid();
    $db->prepare("INSERT INTO fbgdsd_groups (id,hospital_id,group_name,lead_nurse,district,region,created_by) VALUES (:id,:hid,:name,:nurse,:dist,:reg,:by)")
       ->execute(['id' => $groupId, 'hid' => $hospitalId, 'name' => $groupName, 'nurse' => $leadNurse, 'dist' => $district, 'reg' => $region, 'by' => $user['id']]);
}

// Create meeting record
$meetingId = uid();
$db->prepare("INSERT INTO fbgdsd_meetings (id,group_id,hospital_id,meeting_date,topic,created_by) VALUES (:id,:gid,:hid,:dt,:topic,:by)")
   ->execute(['id' => $meetingId, 'gid' => $groupId, 'hid' => $hospitalId, 'dt' => $meetingDate ?: date('Y-m-d'), 'topic' => $topic, 'by' => $user['id']]);

// Import patient rows
$imported = 0;
$skipped  = 0;
$insMember = $db->prepare("INSERT INTO fbgdsd_members
    (id,group_id,hospital_id,first_name,surname,age,sex,educational_level,art_reg_number,drug_regimen,
     level_of_care,vl_suppressed,cd4_above_200,disclosure_done,disclosure_date,
     eac_eligible,eac_date_initiated,vl_date_taken,vl_result,
     ahd_date_done,ahd_result,tb_date_done,tb_result,tpt_eligible,tpt_provided)
    VALUES
    (:id,:gid,:hid,:fn,:sn,:age,:sex,:edu,:art,:reg,
     :loc,:vls,:cd4,:disc,:ddate,
     :eac,:edate,:vdt,:vres,
     :ahd,:ares,:tbd,:tbres,:tpte,:tptp)
    ON DUPLICATE KEY UPDATE
    first_name=VALUES(first_name),surname=VALUES(surname),age=VALUES(age),
    drug_regimen=VALUES(drug_regimen),updated_at=NOW()");

$insAtt = $db->prepare("INSERT IGNORE INTO fbgdsd_attendance (id,meeting_id,member_id,attended) VALUES (:id,:mid,:mbid,1)");

for ($i = $dataStart; $i < count($register); $i++) {
    $row = $register[$i];
    $art = cell($row, 7);
    if (!$art || str_starts_with($art, '=')) { $skipped++; continue; }

    $firstName = cell($row, 1);
    $surname   = cell($row, 2);
    $age       = (int)cell($row, 3) ?: null;
    $sex       = cell($row, 4) ? 'M' : (cell($row, 5) ? 'F' : null);
    $edu       = cell($row, 6);
    $regimen   = cell($row, 8) ? 'first_line' : (cell($row, 9) ? 'second_line' : null);
    $loc       = cell($row, 10) ? 'established' : (cell($row, 11) ? 'non_established' : null);
    $vlSup     = cell($row, 13) ? 1 : (cell($row, 12) ? 0 : null);
    $cd4       = cell($row, 14) ? 1 : (cell($row, 15) ? 0 : null);
    $discDone  = (int)(bool)cell($row, 16);
    $discDate  = ymd(cell($row, 17));
    $eacElig   = (int)(bool)cell($row, 18);
    $eacDate   = ymd(cell($row, 19));
    $vlDate    = ymd(cell($row, 20));
    $vlRes     = cell($row, 21);
    $ahdDate   = ymd(cell($row, 22));
    $ahdRes    = cell($row, 23);
    $tbDate    = ymd(cell($row, 24));
    $tbRes     = cell($row, 25);
    $tptElig   = (int)(bool)cell($row, 26);
    $tptProv   = (int)(bool)cell($row, 27);

    $memberId = uid();
    $insMember->execute([
        'id' => $memberId, 'gid' => $groupId, 'hid' => $hospitalId,
        'fn' => $firstName, 'sn' => $surname, 'age' => $age, 'sex' => $sex,
        'edu' => $edu, 'art' => $art, 'reg' => $regimen, 'loc' => $loc,
        'vls' => $vlSup, 'cd4' => $cd4,
        'disc' => $discDone, 'ddate' => $discDate,
        'eac' => $eacElig, 'edate' => $eacDate,
        'vdt' => $vlDate, 'vres' => $vlRes,
        'ahd' => $ahdDate, 'ares' => $ahdRes,
        'tbd' => $tbDate, 'tbres' => $tbRes,
        'tpte' => $tptElig, 'tptp' => $tptProv,
    ]);

    // Link attendance to this meeting
    $actualId = $db->prepare("SELECT id FROM fbgdsd_members WHERE group_id=:g AND art_reg_number=:art LIMIT 1");
    $actualId->execute(['g' => $groupId, 'art' => $art]);
    $mbRow = $actualId->fetch();
    if ($mbRow) {
        $insAtt->execute(['id' => uid(), 'mid' => $meetingId, 'mbid' => $mbRow['id']]);
    }
    $imported++;
}

ok([
    'group_id'   => $groupId,
    'meeting_id' => $meetingId,
    'imported'   => $imported,
    'skipped'    => $skipped,
    'meeting_date' => $meetingDate,
    'group_name'   => $groupName,
], "Import complete: $imported patients imported");
