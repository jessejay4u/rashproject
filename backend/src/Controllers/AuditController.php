<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use PDO;

class AuditController
{
    public function __construct(
        private readonly AuthMiddleware $auth = new AuthMiddleware()
    ) {}

    public function index(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'audit.view');

        $db      = Database::connection();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        $conditions = ['1=1'];
        $params     = [];

        if (!empty($_GET['user_id'])) {
            $conditions[] = 'al.user_id = :user_id';
            $params['user_id'] = $_GET['user_id'];
        }
        if (!empty($_GET['action'])) {
            $conditions[] = 'al.action ILIKE :action';
            $params['action'] = '%' . $_GET['action'] . '%';
        }
        if (!empty($_GET['entity_type'])) {
            $conditions[] = 'al.entity_type = :entity_type';
            $params['entity_type'] = $_GET['entity_type'];
        }
        if (!empty($_GET['from'])) {
            $conditions[] = 'al.created_at >= :from';
            $params['from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $conditions[] = 'al.created_at <= :to';
            $params['to'] = $_GET['to'];
        }

        $where = implode(' AND ', $conditions);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT al.*, u.name AS user_name
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE $where
            ORDER BY al.created_at DESC
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
}
