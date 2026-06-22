<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Config\Redis;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use PDO;

class FormController
{
    public function __construct(
        private readonly AuthMiddleware $auth  = new AuthMiddleware(),
        private readonly AuditService  $audit = new AuditService()
    ) {}

    public function index(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'forms.view');

        $db      = Database::connection();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        $conditions = ['1=1'];
        $params     = [];

        if (!empty($_GET['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $_GET['status'];
        }
        if (!empty($_GET['category'])) {
            $conditions[] = 'category = :category';
            $params['category'] = $_GET['category'];
        }
        if (!empty($_GET['search'])) {
            $conditions[] = "(name ILIKE :search OR description ILIKE :search)";
            $params['search'] = '%' . $_GET['search'] . '%';
        }

        // Non-admins only see published forms
        if (!in_array($user['role_name'], ['super_admin', 'regional_admin'], true)) {
            $conditions[] = "status = 'published'";
        }

        $where = implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM forms WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT f.*, u.name AS created_by_name,
                   (SELECT COUNT(*) FROM form_fields WHERE form_id = f.id) AS field_count
            FROM forms f
            JOIN users u ON u.id = f.created_by
            WHERE $where
            ORDER BY f.updated_at DESC
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

        Response::paginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function show(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'forms.view');

        $cacheKey = 'form:full:' . $id;
        $redis    = Redis::connection();
        $cached   = $redis->get($cacheKey);

        if ($cached) {
            Response::success($cached);
            return;
        }

        $db   = Database::connection();
        $stmt = $db->prepare('SELECT * FROM forms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $form = $stmt->fetch();

        if (!$form) {
            Response::notFound('Form not found');
            return;
        }

        // Fetch sections with fields
        $sectStmt = $db->prepare('SELECT * FROM form_sections WHERE form_id = :id ORDER BY order_index');
        $sectStmt->execute(['id' => $id]);
        $sections = $sectStmt->fetchAll();

        $fieldStmt = $db->prepare('SELECT * FROM form_fields WHERE form_id = :id ORDER BY order_index');
        $fieldStmt->execute(['id' => $id]);
        $fields = $fieldStmt->fetchAll();

        // Map fields to sections
        $fieldsBySection = [];
        foreach ($fields as $field) {
            $field['options']    = json_decode($field['options'] ?? '[]', true);
            $field['validation'] = json_decode($field['validation'] ?? '{}', true);
            $field['conditions'] = json_decode($field['conditions'] ?? '[]', true);
            $fieldsBySection[$field['section_id']][] = $field;
        }

        foreach ($sections as &$section) {
            $section['fields'] = $fieldsBySection[$section['id']] ?? [];
        }

        $form['sections'] = $sections;
        $form['settings'] = json_decode($form['settings'] ?? '{}', true);

        $redis->setex($cacheKey, 300, $form);
        Response::success($form);
    }

    public function store(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'forms.create');

        $body = $this->parseBody();

        $v = Validator::make($body, [
            'name'     => 'required|string|max:200',
            'code'     => 'required|string|max:50',
            'sections' => 'required|array',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        $db     = Database::connection();
        $formId = $this->generateUuid();

        $db->beginTransaction();
        try {
            $db->prepare("
                INSERT INTO forms (id, created_by, name, code, description, category, version, status, is_recurring, recurrence_type, settings)
                VALUES (:id, :uid, :name, :code, :desc, :cat, 1, 'draft', :recurring, :rectype, :settings)
            ")->execute([
                'id'       => $formId,
                'uid'      => $user['id'],
                'name'     => $body['name'],
                'code'     => strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $body['code'])),
                'desc'     => $body['description'] ?? null,
                'cat'      => $body['category'] ?? null,
                'recurring'=> !empty($body['is_recurring']) ? 'true' : 'false',
                'rectype'  => $body['recurrence_type'] ?? null,
                'settings' => json_encode($body['settings'] ?? []),
            ]);

            foreach ($body['sections'] as $sIndex => $section) {
                $sectionId = $this->generateUuid();
                $db->prepare("
                    INSERT INTO form_sections (id, form_id, title, description, order_index, is_repeatable)
                    VALUES (:id, :fid, :title, :desc, :order, :repeatable)
                ")->execute([
                    'id'        => $sectionId,
                    'fid'       => $formId,
                    'title'     => $section['title'],
                    'desc'      => $section['description'] ?? null,
                    'order'     => $sIndex,
                    'repeatable'=> !empty($section['is_repeatable']) ? 'true' : 'false',
                ]);

                foreach ($section['fields'] ?? [] as $fIndex => $field) {
                    $db->prepare("
                        INSERT INTO form_fields (id, section_id, form_id, name, label, field_type, is_required, order_index, placeholder, help_text, default_value, options, validation, conditions)
                        VALUES (:id, :sid, :fid, :name, :label, :type, :required, :order, :placeholder, :help, :default, :options, :validation, :conditions)
                    ")->execute([
                        'id'         => $this->generateUuid(),
                        'sid'        => $sectionId,
                        'fid'        => $formId,
                        'name'       => strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $field['name'])),
                        'label'      => $field['label'],
                        'type'       => $field['field_type'],
                        'required'   => !empty($field['is_required']) ? 'true' : 'false',
                        'order'      => $fIndex,
                        'placeholder'=> $field['placeholder'] ?? null,
                        'help'       => $field['help_text'] ?? null,
                        'default'    => $field['default_value'] ?? null,
                        'options'    => json_encode($field['options'] ?? []),
                        'validation' => json_encode($field['validation'] ?? []),
                        'conditions' => json_encode($field['conditions'] ?? []),
                    ]);
                }
            }

            $db->commit();
            $this->audit->log($user['id'], 'form.create', 'form', $formId);
            Response::success(['id' => $formId], 'Form created', 201);
        } catch (\Exception $e) {
            $db->rollBack();
            error_log('Form create failed: ' . $e->getMessage());
            Response::error('Failed to create form', 500);
        }
    }

    public function publish(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'forms.publish');

        $db   = Database::connection();
        $form = $db->prepare("SELECT * FROM forms WHERE id = :id AND status = 'draft'");
        $form->execute(['id' => $id]);
        $form = $form->fetch();

        if (!$form) {
            Response::notFound('Form not found or already published');
            return;
        }

        $db->prepare("UPDATE forms SET status = 'published', published_at = NOW(), updated_at = NOW() WHERE id = :id")
           ->execute(['id' => $id]);

        Redis::connection()->del('form:full:' . $id);

        $this->audit->log($user['id'], 'form.publish', 'form', $id);
        Response::success(null, 'Form published');
    }

    public function archive(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'forms.edit');

        Database::connection()->prepare(
            "UPDATE forms SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = :id"
        )->execute(['id' => $id]);

        Redis::connection()->del('form:full:' . $id);

        $this->audit->log($user['id'], 'form.archive', 'form', $id);
        Response::success(null, 'Form archived');
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
