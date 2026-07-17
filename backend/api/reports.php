<?php
require_once __DIR__ . '/bootstrap.php';

$user = auth();
$db   = db();

need($user, 'reports.export');

$resource = qp('resource');
$export   = qp('export');

$where  = ['1=1'];
$params = [];

if (qp('form_id'))     { $where[] = 's.form_id = :form_id';        $params['form_id']     = qp('form_id'); }
if (qp('hospital_id')) { $where[] = 's.hospital_id = :hospital_id'; $params['hospital_id'] = qp('hospital_id'); }
if (qp('from'))        { $where[] = 's.submitted_at >= :from';       $params['from']        = qp('from'); }
if (qp('to'))          { $where[] = 's.submitted_at <= :to';         $params['to']          = qp('to'); }
if (qp('status'))      { $where[] = 's.status = :status';            $params['status']      = qp('status'); }

// Scope
if ($user['role'] === 'data_entry')      { $where[] = 's.submitted_by = :uid'; $params['uid'] = $user['id']; }
if ($user['role'] === 'hospital_admin')  { $where[] = 's.hospital_id = :hid';  $params['hid'] = $user['hospital_id']; }
if ($user['role'] === 'regional_admin')  { $where[] = 'h.region_id = :rid';   $params['rid'] = $user['region_id']; }

$w = implode(' AND ', $where);

function fetchFilteredSubmissions(PDO $db, string $w, array $params, int $limit = 10000): array {
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
        LIMIT $limit
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ─── Preview: paginated JSON so the Reports page can show the data before export ──
if ($resource === 'preview') {
    $page    = max(1, (int) qp('page', 1));
    $perPage = 50;
    $offset  = ($page - 1) * $perPage;

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM submissions s
        JOIN hospitals h ON h.id = s.hospital_id
        WHERE $w
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT s.id, f.name AS form, h.name AS hospital, r.name AS region,
               u.name AS submitted_by, s.status, s.period_start, s.period_end,
               s.submitted_at, s.reviewed_at
        FROM submissions s
        JOIN forms f ON f.id = s.form_id
        JOIN hospitals h ON h.id = s.hospital_id
        JOIN regions r ON r.id = h.region_id
        JOIN users u ON u.id = s.submitted_by
        WHERE $w
        ORDER BY s.submitted_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    paginate($stmt->fetchAll(), $total, $page, $perPage);
}

// ─── CSV export ─────────────────────────────────────────────────────────────────
if ($export === 'xlsx') {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        fail('Excel export requires the PhpSpreadsheet library. In the backend/ directory run: composer require phpoffice/phpspreadsheet', 500);
    }
    require_once $autoload;

    $rows = fetchFilteredSubmissions($db, $w, $params);

    $byStatusStmt = $db->prepare("
        SELECT s.status AS label, COUNT(*) AS count FROM submissions s
        JOIN hospitals h ON h.id = s.hospital_id WHERE $w GROUP BY s.status
    ");
    $byStatusStmt->execute($params);
    $byStatus = $byStatusStmt->fetchAll();

    $byFormStmt = $db->prepare("
        SELECT f.name AS label, COUNT(*) AS count FROM submissions s
        JOIN forms f ON f.id = s.form_id JOIN hospitals h ON h.id = s.hospital_id
        WHERE $w GROUP BY f.id, f.name ORDER BY count DESC LIMIT 15
    ");
    $byFormStmt->execute($params);
    $byForm = $byFormStmt->fetchAll();

    $byHospitalStmt = $db->prepare("
        SELECT h.name AS label, COUNT(*) AS count FROM submissions s
        JOIN hospitals h ON h.id = s.hospital_id
        WHERE $w GROUP BY h.id, h.name ORDER BY count DESC LIMIT 15
    ");
    $byHospitalStmt->execute($params);
    $byHospital = $byHospitalStmt->fetchAll();

    $trendStmt = $db->prepare("
        SELECT DATE(s.submitted_at) AS label, COUNT(*) AS count FROM submissions s
        JOIN hospitals h ON h.id = s.hospital_id
        WHERE $w AND s.submitted_at IS NOT NULL
        GROUP BY label ORDER BY label LIMIT 90
    ");
    $trendStmt->execute($params);
    $trend = $trendStmt->fetchAll();

    buildAndStreamReportXlsx($rows, $byStatus, $byForm, $byHospital, $trend);
    exit;
}

// Default / explicit CSV
$rows = fetchFilteredSubmissions($db, $w, $params);

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

// ─── Excel builder (native charts via PhpSpreadsheet) ────────────────────────────
function buildAndStreamReportXlsx(array $rows, array $byStatus, array $byForm, array $byHospital, array $trend): void {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getProperties()->setTitle('Submissions Report')->setCreator('Health Platform');

    // ── Sheet 1: raw data ──────────────────────────────────────────────────────
    $data = $spreadsheet->getActiveSheet();
    $data->setTitle('Submissions');
    if (!empty($rows)) {
        $headers = array_keys($rows[0]);
        $data->fromArray($headers, null, 'A1');
        $data->fromArray(array_map('array_values', $rows), null, 'A2');
        foreach (range('A', $data->getHighestColumn()) as $col) {
            $data->getColumnDimension($col)->setAutoSize(true);
        }
        $data->getStyle('A1:' . $data->getHighestColumn() . '1')->getFont()->setBold(true);
    } else {
        $data->fromArray(['No submissions matched the selected filters'], null, 'A1');
    }

    // ── Sheet 2: chart source data + native charts ────────────────────────────
    $chartSheet = $spreadsheet->createSheet();
    $chartSheet->setTitle('Charts');

    $nextChartCol = 'A';
    $charts = [];

    if (!empty($byStatus)) {
        writeChartTable($chartSheet, 'A1', 'By Status', $byStatus);
        $charts[] = makePieChart($chartSheet, 'By Status', 'A', count($byStatus), 'D2');
    }
    if (!empty($byForm)) {
        writeChartTable($chartSheet, 'G1', 'By Form', $byForm);
        $charts[] = makeBarChart($chartSheet, 'By Form', 'G', count($byForm), 'D22');
    }
    if (!empty($byHospital)) {
        writeChartTable($chartSheet, 'M1', 'By Hospital', $byHospital);
        $charts[] = makeBarChart($chartSheet, 'By Hospital', 'M', count($byHospital), 'D42');
    }
    if (!empty($trend)) {
        writeChartTable($chartSheet, 'S1', 'Trend', $trend);
        $charts[] = makeLineChart($chartSheet, 'Trend', 'S', count($trend), 'D62');
    }
    foreach ($charts as $chart) {
        $chartSheet->addChart($chart);
    }

    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="submissions_report_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    $writer->save('php://output');
}

function writeChartTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $anchor, string $title, array $rows): void {
    $col = preg_replace('/\d+/', '', $anchor);
    $row = (int) preg_replace('/\D+/', '', $anchor);
    $sheet->setCellValue("{$col}{$row}", $title);
    $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
    $labelCol = $col;
    $valueCol = chr(ord($col) + 1);
    $sheet->setCellValue("{$labelCol}" . ($row + 1), 'Label');
    $sheet->setCellValue("{$valueCol}" . ($row + 1), 'Count');
    $i = $row + 2;
    foreach ($rows as $r) {
        $sheet->setCellValue("{$labelCol}{$i}", (string)($r['label'] ?? $r['status'] ?? ''));
        $sheet->setCellValue("{$valueCol}{$i}", (int)($r['count'] ?? 0));
        $i++;
    }
}

function chartDataSeries(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $col, int $count, string $chartType): \PhpOffice\PhpSpreadsheet\Chart\DataSeries {
    $sheetName = $sheet->getTitle();
    $labelCol  = $col;
    $valueCol  = chr(ord($col) + 1);

    $labels = [new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
        \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING,
        "'{$sheetName}'!\${$labelCol}\$3:\${$labelCol}\$" . (2 + $count), null, $count
    )];
    $values = [new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
        \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_NUMBER,
        "'{$sheetName}'!\${$valueCol}\$3:\${$valueCol}\$" . (2 + $count), null, $count
    )];

    return new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
        $chartType, null, range(0, count($values) - 1), [], $labels, $values
    );
}

function makePieChart(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, string $col, int $count, string $position): \PhpOffice\PhpSpreadsheet\Chart\Chart {
    $series   = chartDataSeries($sheet, $col, $count, \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_PIECHART);
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
    $legend   = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_RIGHT, null, false);
    $chart    = new \PhpOffice\PhpSpreadsheet\Chart\Chart(uniqid('chart_'), new \PhpOffice\PhpSpreadsheet\Chart\Title($title), $legend, $plotArea);
    $chart->setTopLeftPosition($position);
    $chart->setBottomRightPosition(shiftCell($position, 8, 18));
    return $chart;
}

function makeBarChart(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, string $col, int $count, string $position): \PhpOffice\PhpSpreadsheet\Chart\Chart {
    $series   = chartDataSeries($sheet, $col, $count, \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART);
    $series->setPlotDirection(\PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COL);
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
    $legend   = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
    $chart    = new \PhpOffice\PhpSpreadsheet\Chart\Chart(uniqid('chart_'), new \PhpOffice\PhpSpreadsheet\Chart\Title($title), $legend, $plotArea);
    $chart->setTopLeftPosition($position);
    $chart->setBottomRightPosition(shiftCell($position, 8, 18));
    return $chart;
}

function makeLineChart(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, string $col, int $count, string $position): \PhpOffice\PhpSpreadsheet\Chart\Chart {
    $series   = chartDataSeries($sheet, $col, $count, \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_LINECHART);
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
    $legend   = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
    $chart    = new \PhpOffice\PhpSpreadsheet\Chart\Chart(uniqid('chart_'), new \PhpOffice\PhpSpreadsheet\Chart\Title($title), $legend, $plotArea);
    $chart->setTopLeftPosition($position);
    $chart->setBottomRightPosition(shiftCell($position, 8, 18));
    return $chart;
}

function shiftCell(string $cell, int $cols, int $rows): string {
    $col = preg_replace('/\d+/', '', $cell);
    $row = (int) preg_replace('/\D+/', '', $cell);
    return chr(ord($col) + $cols) . ($row + $rows);
}
