<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use PDO;

class HospitalController
{
    public function __construct(
        private readonly AuthMiddleware $auth  = new AuthMiddleware(),
        private readonly AuditService  $audit = new AuditService()
    ) {}

    public function index(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'hospitals.view');

        $db      = Database::connection();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        $conditions = ['h.is_active = TRUE'];
        $params     = [];

        if (!empty($_GET['region_id'])) {
            $conditions[] = 'h.region_id = :region_id';
            $params['region_id'] = $_GET['region_id'];
        } elseif ($user['role_name'] === 'regional_admin' && $user['region_id']) {
            $conditions[] = 'h.region_id = :region_id';
            $params['region_id'] = $user['region_id'];
        }

        if (!empty($_GET['search'])) {
            $conditions[] = "(h.name LIKE :search OR h.code LIKE :search)";
            $params['search'] = '%' . $_GET['search'] . '%';
        }

        if (!empty($_GET['type'])) {
            $conditions[] = 'h.type = :type';
            $params['type'] = $_GET['type'];
        }

        $where = implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM hospitals h WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT h.*, r.name AS region_name,
                   (SELECT COUNT(*) FROM submissions s WHERE s.hospital_id = h.id AND s.submitted_at >= NOW() - INTERVAL 30 DAY) AS monthly_submissions,
                   (SELECT MAX(submitted_at) FROM submissions s WHERE s.hospital_id = h.id) AS last_submission
            FROM hospitals h
            JOIN regions r ON r.id = h.region_id
            WHERE $where
            ORDER BY h.name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            if (!in_array($key, ['limit', 'offset'], true)) $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        Response::paginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function store(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'hospitals.create');

        $body = $this->parseBody();
        $v    = Validator::make($body, [
            'name'      => 'required|string|max:200',
            'code'      => 'required|string|max:30',
            'region_id' => 'required|uuid',
            'type'      => 'required|in:general,specialized,clinic,health_post',
        ]);

        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        $db  = Database::connection();
        $id  = $this->generateUuid();

        $db->prepare("
            INSERT INTO hospitals (id, region_id, name, code, type, level, address, phone, email, latitude, longitude, capacity_beds, metadata)
            VALUES (:id, :region_id, :name, :code, :type, :level, :address, :phone, :email, :lat, :lon, :beds, :meta)
        ")->execute([
            'id'        => $id,
            'region_id' => $body['region_id'],
            'name'      => $body['name'],
            'code'      => strtoupper($body['code']),
            'type'      => $body['type'],
            'level'     => $body['level'] ?? 1,
            'address'   => $body['address'] ?? null,
            'phone'     => $body['phone'] ?? null,
            'email'     => $body['email'] ?? null,
            'lat'       => $body['latitude'] ?? null,
            'lon'       => $body['longitude'] ?? null,
            'beds'      => $body['capacity_beds'] ?? null,
            'meta'      => json_encode($body['metadata'] ?? []),
        ]);

        $this->audit->log($user['id'], 'hospital.create', 'hospital', $id, null, $body);
        Response::success(['id' => $id], 'Hospital created', 201);
    }

    public function show(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'hospitals.view');

        $db   = Database::connection();
        $stmt = $db->prepare("
            SELECT h.*, r.name AS region_name,
                   (SELECT COUNT(*) FROM users WHERE hospital_id = h.id AND is_active = TRUE) AS user_count,
                   (SELECT COUNT(*) FROM submissions WHERE hospital_id = h.id) AS total_submissions,
                   (SELECT MAX(submitted_at) FROM submissions WHERE hospital_id = h.id) AS last_submission
            FROM hospitals h JOIN regions r ON r.id = h.region_id WHERE h.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $hospital = $stmt->fetch();

        if (!$hospital) {
            Response::notFound('Hospital not found');
            return;
        }

        Response::success($hospital);
    }

    public function update(string $id): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'hospitals.edit');

        $body = $this->parseBody();
        $db   = Database::connection();

        $old = $db->prepare('SELECT * FROM hospitals WHERE id = :id');
        $old->execute(['id' => $id]);
        $old = $old->fetch();

        if (!$old) {
            Response::notFound('Hospital not found');
            return;
        }

        $fields = ['name', 'type', 'level', 'address', 'phone', 'email', 'latitude', 'longitude', 'capacity_beds'];
        $sets   = ['updated_at = NOW()'];
        $params = ['id' => $id];

        foreach ($fields as $field) {
            if (array_key_exists($field, $body)) {
                $col          = match ($field) { 'latitude' => 'latitude', 'longitude' => 'longitude', default => $field };
                $sets[]       = "$col = :$field";
                $params[$field] = $body[$field];
            }
        }

        $db->prepare('UPDATE hospitals SET ' . implode(', ', $sets) . ' WHERE id = :id')
           ->execute($params);

        $this->audit->log($user['id'], 'hospital.update', 'hospital', $id, $old, $body);
        Response::success(null, 'Hospital updated');
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
