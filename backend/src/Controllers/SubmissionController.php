<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use PDO;

class SubmissionController
{
    public function __construct(
        private readonly AuthMiddleware $auth  = new AuthMiddleware(),
        private readonly AuditService  $audit = new AuditService()
    ) {}

    public function index(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'submissions.view');

        $db      = Database::connection();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildSubmissionFilters($user);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM submissions s WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT s.*, f.name AS form_name, h.name AS hospital_name, u.name AS submitted_by_name
            FROM submissions s
            JOIN forms f ON f.id = s.form_id
            JOIN hospitals h ON h.id = s.hospital_id
            JOIN users u ON u.id = s.submitted_by
            WHERE $where
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            if (!in_array($key, ['limit', 'offset'], true)) {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        $submissions = $stmt->fetchAll();

        Response::paginated($submissions, $total, $page, $perPage);
    }

    public function store(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'submissions.create');

        $body = $this->parseBody();

        $v = Validator::make($body, [
            'form_id'     => 'required|uuid',
            'hospital_id' => 'required|uuid',
            'values'      => 'required|array',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        // Data-entry users can only submit for their own hospital
        if ($user['role_name'] === 'data_entry' && $user['hospital_id'] !== $body['hospital_id']) {
            Response::forbidden('You can only submit data for your assigned hospital');
            return;
        }

        $db = Database::connection();

        $form = $db->prepare('SELECT * FROM forms WHERE id = :id AND status = \'published\'');
        $form->execute(['id' => $body['form_id']]);
        $form = $form->fetch();

        if (!$form) {
            Response::notFound('Form not found or not published');
            return;
        }

        // Validate form field values
        $validationErrors = $this->validateFormValues($body['form_id'], $body['values']);
        if (!empty($validationErrors)) {
            Response::validationError($validationErrors, 'Form validation failed');
            return;
        }

        $db->beginTransaction();
        try {
            $submissionId = $this->generateUuid();
            $status       = ($body['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
            $submittedAt  = $status === 'submitted' ? 'NOW()' : 'NULL';

            $db->prepare("
                INSERT INTO submissions (id, form_id, form_version, hospital_id, submitted_by, status, period_start, period_end, submitted_at, latitude, longitude, device_id, device_info, local_id, duration_seconds)
                VALUES (:id, :form_id, :version, :hospital_id, :user_id, :status, :period_start, :period_end, $submittedAt, :lat, :lon, :device_id, :device_info, :local_id, :duration)
            ")->execute([
                'id'           => $submissionId,
                'form_id'      => $body['form_id'],
                'version'      => $form['version'],
                'hospital_id'  => $body['hospital_id'],
                'user_id'      => $user['id'],
                'status'       => $status,
                'period_start' => $body['period_start'] ?? null,
                'period_end'   => $body['period_end'] ?? null,
                'lat'          => $body['latitude'] ?? null,
                'lon'          => $body['longitude'] ?? null,
                'device_id'    => $body['device_id'] ?? null,
                'device_info'  => json_encode($body['device_info'] ?? []),
                'local_id'     => $body['local_id'] ?? null,
                'duration'     => $body['duration_seconds'] ?? null,
            ]);

            // Insert field values
            $valueStmt = $db->prepare("
                INSERT INTO submission_values (id, submission_id, field_id, field_name, value_text, value_number, value_date, value_boolean, value_json)
                VALUES (:id, :sub_id, :field_id, :field_name, :text, :number, :date, :boolean, :json)
            ");

            foreach ($body['values'] as $fieldId => $value) {
                $field = $this->getField($fieldId);
                if (!$field) continue;

                $valueStmt->execute([
                    'id'         => $this->generateUuid(),
                    'sub_id'     => $submissionId,
                    'field_id'   => $fieldId,
                    'field_name' => $field['name'],
                    'text'       => is_string($value) ? $value : null,
                    'number'     => is_numeric($value) ? $value : null,
                    'date'       => ($field['field_type'] === 'date') ? $value : null,
                    'boolean'    => is_bool($value) ? ($value ? 'true' : 'false') : null,
                    'json'       => is_array($value) ? json_encode($value) : null,
                ]);
            }

            $db->commit();

            $this->audit->log($user['id'], 'submission.create', 'submission', $submissionId);
            Response::success(['id' => $submissionId], 'Submission saved successfully', 201);
        } catch (\Exception $e) {
            $db->rollBack();
            error_log('Submission store failed: ' . $e->getMessage());
            Response::error('Failed to save submission', 500);
        }
    }

    public function show(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'submissions.view');

        $db   = Database::connection();
        $stmt = $db->prepare("
            SELECT s.*, f.name AS form_name, h.name AS hospital_name, u.name AS submitted_by_name,
                   r.name AS reviewer_name
            FROM submissions s
            JOIN forms f ON f.id = s.form_id
            JOIN hospitals h ON h.id = s.hospital_id
            JOIN users u ON u.id = s.submitted_by
            LEFT JOIN users r ON r.id = s.reviewed_by
            WHERE s.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $submission = $stmt->fetch();

        if (!$submission) {
            Response::notFound('Submission not found');
            return;
        }

        // Data-entry users can only see their own hospital's submissions
        if ($user['role_name'] === 'data_entry' && $submission['hospital_id'] !== $user['hospital_id']) {
            Response::forbidden();
            return;
        }

        // Fetch field values
        $valStmt = $db->prepare("
            SELECT sv.*, ff.label, ff.field_type
            FROM submission_values sv
            JOIN form_fields ff ON ff.id = sv.field_id
            WHERE sv.submission_id = :id
            ORDER BY ff.order_index
        ");
        $valStmt->execute(['id' => $id]);
        $submission['values'] = $valStmt->fetchAll();

        // Fetch attachments
        $attStmt = $db->prepare('SELECT * FROM submission_attachments WHERE submission_id = :id');
        $attStmt->execute(['id' => $id]);
        $submission['attachments'] = $attStmt->fetchAll();

        Response::success($submission);
    }

    public function review(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'submissions.review');

        $body = $this->parseBody();

        $v = Validator::make($body, [
            'status' => 'required|in:approved,rejected',
            'notes'  => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        $db   = Database::connection();
        $stmt = $db->prepare('SELECT * FROM submissions WHERE id = :id AND status = \'submitted\'');
        $stmt->execute(['id' => $id]);
        $submission = $stmt->fetch();

        if (!$submission) {
            Response::notFound('Submission not found or not in submitted state');
            return;
        }

        $db->prepare("
            UPDATE submissions SET status = :status, reviewed_by = :reviewer, reviewed_at = NOW(), review_notes = :notes, updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'status'   => $body['status'],
            'reviewer' => $user['id'],
            'notes'    => $body['notes'] ?? null,
            'id'       => $id,
        ]);

        $this->audit->log($user['id'], 'submission.review', 'submission', $id, ['status' => $submission['status']], ['status' => $body['status']]);
        Response::success(null, 'Submission ' . $body['status']);
    }

    public function syncBatch(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'submissions.create');

        $body        = $this->parseBody();
        $submissions = $body['submissions'] ?? [];

        if (!is_array($submissions) || empty($submissions)) {
            Response::error('No submissions provided', 400);
            return;
        }

        $results = [];
        foreach (array_slice($submissions, 0, 50) as $sub) { // max 50 per batch
            try {
                $_POST  = $sub;
                $sub['status'] = 'submitted';
                $results[] = ['local_id' => $sub['local_id'] ?? null, 'status' => 'queued'];
            } catch (\Exception $e) {
                $results[] = ['local_id' => $sub['local_id'] ?? null, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        Response::success(['results' => $results, 'synced_at' => date('c')], 'Batch sync processed');
    }

    private function buildSubmissionFilters(array $user): array
    {
        $conditions = ['1=1'];
        $params     = [];

        // Scope by role
        if ($user['role_name'] === 'data_entry') {
            $conditions[] = 's.submitted_by = :user_id';
            $params['user_id'] = $user['id'];
        } elseif ($user['role_name'] === 'hospital_admin') {
            $conditions[] = 's.hospital_id = :hospital_id';
            $params['hospital_id'] = $user['hospital_id'];
        } elseif ($user['role_name'] === 'regional_admin') {
            $conditions[] = 'h.region_id = :region_id';
            $params['region_id'] = $user['region_id'];
        }

        // Filters from query params
        if (!empty($_GET['form_id'])) {
            $conditions[]      = 's.form_id = :form_id';
            $params['form_id'] = $_GET['form_id'];
        }
        if (!empty($_GET['hospital_id'])) {
            $conditions[]          = 's.hospital_id = :hospital_id_f';
            $params['hospital_id_f'] = $_GET['hospital_id'];
        }
        if (!empty($_GET['status'])) {
            $conditions[]     = 's.status = :status';
            $params['status'] = $_GET['status'];
        }
        if (!empty($_GET['from'])) {
            $conditions[]  = 's.submitted_at >= :from';
            $params['from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $conditions[] = 's.submitted_at <= :to';
            $params['to'] = $_GET['to'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function validateFormValues(string $formId, array $values): array
    {
        $db     = Database::connection();
        $errors = [];

        $stmt = $db->prepare('SELECT * FROM form_fields WHERE form_id = :fid ORDER BY order_index');
        $stmt->execute(['fid' => $formId]);
        $fields = $stmt->fetchAll();

        foreach ($fields as $field) {
            $value = $values[$field['id']] ?? null;

            if ($field['is_required'] && ($value === null || $value === '')) {
                $errors[$field['id']] = "The field '{$field['label']}' is required.";
                continue;
            }

            if ($value === null) continue;

            $validation = json_decode($field['validation'] ?? '{}', true);
            if (!empty($validation['min']) && is_numeric($value) && $value < $validation['min']) {
                $errors[$field['id']] = "'{$field['label']}' must be at least {$validation['min']}.";
            }
            if (!empty($validation['max']) && is_numeric($value) && $value > $validation['max']) {
                $errors[$field['id']] = "'{$field['label']}' must not exceed {$validation['max']}.";
            }
            if (!empty($validation['minLength']) && strlen((string) $value) < $validation['minLength']) {
                $errors[$field['id']] = "'{$field['label']}' must be at least {$validation['minLength']} characters.";
            }
            if (!empty($validation['maxLength']) && strlen((string) $value) > $validation['maxLength']) {
                $errors[$field['id']] = "'{$field['label']}' must not exceed {$validation['maxLength']} characters.";
            }
            if (!empty($validation['pattern']) && !preg_match('/' . $validation['pattern'] . '/', (string) $value)) {
                $errors[$field['id']] = "'{$field['label']}' has an invalid format.";
            }
        }

        return $errors;
    }

    private function getField(string $fieldId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM form_fields WHERE id = :id');
        $stmt->execute(['id' => $fieldId]);
        return $stmt->fetch() ?: null;
    }

    private function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
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
