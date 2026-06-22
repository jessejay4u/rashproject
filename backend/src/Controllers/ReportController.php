<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use PDO;

class ReportController
{
    public function __construct(
        private readonly AuthMiddleware $auth  = new AuthMiddleware(),
        private readonly AuditService  $audit = new AuditService()
    ) {}

    public function index(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'reports.view');

        $db   = Database::connection();
        $stmt = $db->prepare("
            SELECT r.*, u.name AS created_by_name
            FROM reports r
            JOIN users u ON u.id = r.created_by
            WHERE r.created_by = :uid OR :is_admin = TRUE
            ORDER BY r.created_at DESC LIMIT 50
        ");
        $stmt->execute([
            'uid'      => $user['id'],
            'is_admin' => in_array($user['role_name'], ['super_admin', 'regional_admin'], true) ? 'TRUE' : 'FALSE',
        ]);

        Response::success($stmt->fetchAll());
    }

    public function generate(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'reports.generate');

        $body = $this->parseBody();
        $v    = Validator::make($body, [
            'type'   => 'required|in:summary,detailed,comparison,trend',
            'format' => 'required|in:pdf,xlsx,csv',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        $db = Database::connection();
        $id = $this->generateUuid();

        $db->prepare("
            INSERT INTO reports (id, created_by, name, type, parameters, status, file_format, expires_at)
            VALUES (:id, :uid, :name, :type, :params, 'pending', :format, NOW() + INTERVAL '24 hours')
        ")->execute([
            'id'     => $id,
            'uid'    => $user['id'],
            'name'   => $body['name'] ?? ('Report ' . date('Y-m-d H:i')),
            'type'   => $body['type'],
            'params' => json_encode($body['parameters'] ?? []),
            'format' => $body['format'],
        ]);

        // In production this would dispatch a background job
        // For now, generate synchronously for simple reports
        $this->generateReport($id, $body['type'], $body['parameters'] ?? [], $body['format'], $user);

        $this->audit->log($user['id'], 'report.generate', 'report', $id);
        Response::success(['id' => $id, 'status' => 'generating'], 'Report generation started', 202);
    }

    public function download(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'reports.export');

        $db   = Database::connection();
        $stmt = $db->prepare("SELECT * FROM reports WHERE id = :id AND (created_by = :uid OR :is_admin = TRUE)");
        $stmt->execute([
            'id'       => $id,
            'uid'      => $user['id'],
            'is_admin' => in_array($user['role_name'], ['super_admin', 'regional_admin'], true) ? 'TRUE' : 'FALSE',
        ]);
        $report = $stmt->fetch();

        if (!$report) {
            Response::notFound('Report not found');
            return;
        }

        if ($report['status'] !== 'ready') {
            Response::error('Report is not ready yet: ' . $report['status'], 400);
            return;
        }

        $filePath = $report['file_path'];
        if (!file_exists($filePath)) {
            Response::error('Report file not found', 404);
            return;
        }

        $mimeType = match ($report['file_format']) {
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'  => 'text/csv',
            default => 'application/octet-stream',
        };

        $this->audit->log($user['id'], 'report.download', 'report', $id);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
    }

    public function exportSubmissions(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'reports.export');

        $format = $_GET['format'] ?? 'csv';
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            Response::error('Invalid format. Use csv or xlsx.', 400);
            return;
        }

        $db     = Database::connection();
        $params = [];
        $where  = ['1=1'];

        if (!empty($_GET['form_id']))     { $where[] = 's.form_id = :form_id';       $params['form_id']     = $_GET['form_id']; }
        if (!empty($_GET['hospital_id'])) { $where[] = 's.hospital_id = :hospital_id'; $params['hospital_id'] = $_GET['hospital_id']; }
        if (!empty($_GET['from']))        { $where[] = 's.submitted_at >= :from';     $params['from']        = $_GET['from']; }
        if (!empty($_GET['to']))          { $where[] = 's.submitted_at <= :to';       $params['to']          = $_GET['to']; }
        if (!empty($_GET['status']))      { $where[] = 's.status = :status';          $params['status']      = $_GET['status']; }

        // Enforce scope
        if ($user['role_name'] === 'data_entry') {
            $where[] = 's.submitted_by = :uid';
            $params['uid'] = $user['id'];
        } elseif ($user['role_name'] === 'hospital_admin') {
            $where[] = 's.hospital_id = :hid';
            $params['hid'] = $user['hospital_id'];
        }

        $stmt = $db->prepare("
            SELECT s.id, f.name AS form, h.name AS hospital, r.name AS region,
                   u.name AS submitted_by, s.status, s.period_start, s.period_end,
                   s.submitted_at, s.reviewed_at, s.review_notes, s.latitude, s.longitude
            FROM submissions s
            JOIN forms f ON f.id = s.form_id
            JOIN hospitals h ON h.id = s.hospital_id
            JOIN regions r ON r.id = h.region_id
            JOIN users u ON u.id = s.submitted_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.submitted_at DESC
            LIMIT 10000
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $this->audit->log($user['id'], 'report.export', 'submissions', null, null, ['format' => $format, 'count' => count($rows)]);

        if ($format === 'csv') {
            $this->outputCsv($rows, 'submissions_export_' . date('Ymd'));
        } else {
            $this->outputCsv($rows, 'submissions_export_' . date('Ymd')); // xlsx requires library
        }
    }

    private function generateReport(string $id, string $type, array $params, string $format, array $user): void
    {
        $db = Database::connection();

        try {
            // Simple CSV generation; production would use dedicated job + PhpSpreadsheet
            $storagePath = ($_ENV['STORAGE_PATH'] ?? '/tmp') . '/reports';
            @mkdir($storagePath, 0755, true);

            $filePath = $storagePath . '/' . $id . '.' . $format;

            $data = $this->fetchReportData($type, $params, $user);
            file_put_contents($filePath, $this->toCsv($data));

            $db->prepare("UPDATE reports SET status = 'ready', file_path = :path, generated_at = NOW() WHERE id = :id")
               ->execute(['path' => $filePath, 'id' => $id]);
        } catch (\Exception $e) {
            $db->prepare("UPDATE reports SET status = 'failed', error_message = :err WHERE id = :id")
               ->execute(['err' => $e->getMessage(), 'id' => $id]);
        }
    }

    private function fetchReportData(string $type, array $params, array $user): array
    {
        $db = Database::connection();
        $stmt = $db->prepare("
            SELECT s.id, f.name AS form, h.name AS hospital, s.status, s.submitted_at
            FROM submissions s JOIN forms f ON f.id = s.form_id JOIN hospitals h ON h.id = s.hospital_id
            ORDER BY s.submitted_at DESC LIMIT 1000
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function toCsv(array $rows): string
    {
        if (empty($rows)) return '';
        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }

    private function outputCsv(array $rows, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
    }

    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
