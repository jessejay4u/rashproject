<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Config\Redis;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use PDO;

class DashboardController
{
    public function __construct(
        private readonly AuthMiddleware $auth = new AuthMiddleware()
    ) {}

    public function summary(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'dashboard.view');

        $cacheKey = 'dashboard:summary:' . $user['role_name'] . ':' . ($user['hospital_id'] ?? $user['region_id'] ?? 'global');
        $redis    = Redis::connection();
        $cached   = $redis->get($cacheKey);

        if ($cached) {
            Response::success($cached);
            return;
        }

        $db     = Database::connection();
        $filter = $this->buildScopeFilter($user);

        $data = [
            'total_submissions'   => $this->countSubmissions($db, $filter, 'total'),
            'today_submissions'   => $this->countSubmissions($db, $filter, 'today'),
            'week_submissions'    => $this->countSubmissions($db, $filter, 'week'),
            'month_submissions'   => $this->countSubmissions($db, $filter, 'month'),
            'pending_review'      => $this->countSubmissions($db, $filter, 'pending_review'),
            'approved'            => $this->countSubmissions($db, $filter, 'approved'),
            'rejected'            => $this->countSubmissions($db, $filter, 'rejected'),
            'draft'               => $this->countSubmissions($db, $filter, 'draft'),
            'total_hospitals'     => $this->countHospitals($db, $filter),
            'active_hospitals'    => $this->countActiveHospitals($db, $filter),
            'reporting_rate'      => $this->calculateReportingRate($db, $filter),
            'top_forms'           => $this->getTopForms($db, $filter),
            'submissions_by_day'  => $this->getSubmissionsByDay($db, $filter, 30),
            'submissions_by_form' => $this->getSubmissionsByForm($db, $filter),
            'hospital_rankings'   => $this->getHospitalRankings($db, $filter),
            'missing_reports'     => $this->getMissingReports($db, $filter),
        ];

        $redis->setex($cacheKey, 300, $data); // 5-minute cache
        Response::success($data);
    }

    public function kpis(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'dashboard.view');

        $db     = Database::connection();
        $filter = $this->buildScopeFilter($user);

        $period = $_GET['period'] ?? 'month';
        [$start, $end] = $this->getPeriodDates($period);

        $stmt = $db->prepare("
            SELECT iv.*, i.name, i.unit, i.target_value, i.category,
                   h.name AS hospital_name
            FROM indicator_values iv
            JOIN indicators i ON i.id = iv.indicator_id
            LEFT JOIN hospitals h ON h.id = iv.hospital_id
            WHERE iv.period_start >= :start AND iv.period_end <= :end
            AND i.is_active = TRUE
            " . ($filter['hospital_id'] ? "AND iv.hospital_id = :hospital_id" : "") . "
            ORDER BY i.category, i.name
        ");
        $params = ['start' => $start, 'end' => $end];
        if ($filter['hospital_id']) $params['hospital_id'] = $filter['hospital_id'];
        $stmt->execute($params);

        Response::success([
            'period' => ['start' => $start, 'end' => $end, 'label' => $period],
            'kpis'   => $stmt->fetchAll(),
        ]);
    }

    public function map(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'dashboard.view');

        $db   = Database::connection();
        $stmt = $db->prepare("
            SELECT h.id, h.name, h.latitude, h.longitude, h.type, h.level,
                   r.name AS region_name,
                   COUNT(s.id) AS submission_count,
                   MAX(s.submitted_at) AS last_submission
            FROM hospitals h
            JOIN regions r ON r.id = h.region_id
            LEFT JOIN submissions s ON s.hospital_id = h.id AND s.submitted_at >= NOW() - INTERVAL '30 days'
            WHERE h.is_active = TRUE AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
            GROUP BY h.id, r.name
            ORDER BY submission_count DESC
        ");
        $stmt->execute();

        Response::success(['hospitals' => $stmt->fetchAll()]);
    }

    public function trends(): void
    {
        $user = $this->auth->handle();
        $this->auth->requirePermission($user, 'dashboard.view');

        $db     = Database::connection();
        $filter = $this->buildScopeFilter($user);
        $period = $_GET['period'] ?? 'month';
        $formId = $_GET['form_id'] ?? null;

        $trunc = match ($period) {
            'day'   => 'hour',
            'week'  => 'day',
            'month' => 'day',
            'year'  => 'month',
            default => 'day',
        };

        [$start, $end] = $this->getPeriodDates($period);

        $sql = "
            SELECT DATE_TRUNC(:trunc, s.submitted_at) AS period,
                   COUNT(*) AS count,
                   COUNT(DISTINCT s.hospital_id) AS active_hospitals,
                   AVG(s.duration_seconds) AS avg_duration
            FROM submissions s
            JOIN hospitals h ON h.id = s.hospital_id
            WHERE s.submitted_at BETWEEN :start AND :end
            AND s.status != 'draft'
        ";
        $params = ['trunc' => $trunc, 'start' => $start, 'end' => $end];

        if ($filter['hospital_id']) {
            $sql .= " AND s.hospital_id = :hospital_id";
            $params['hospital_id'] = $filter['hospital_id'];
        }
        if ($filter['region_id']) {
            $sql .= " AND h.region_id = :region_id";
            $params['region_id'] = $filter['region_id'];
        }
        if ($formId) {
            $sql .= " AND s.form_id = :form_id";
            $params['form_id'] = $formId;
        }

        $sql .= " GROUP BY period ORDER BY period";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success(['trend' => $stmt->fetchAll(), 'period' => $period]);
    }

    private function countSubmissions(PDO $db, array $filter, string $type): int
    {
        $conditions = ['s.status != \'draft\''];
        $params     = [];

        if ($filter['hospital_id']) {
            $conditions[] = 's.hospital_id = :hospital_id';
            $params['hospital_id'] = $filter['hospital_id'];
        } elseif ($filter['region_id']) {
            $conditions[] = 'h.region_id = :region_id';
            $params['region_id'] = $filter['region_id'];
        }

        $conditions[] = match ($type) {
            'today'          => "DATE(s.submitted_at) = CURRENT_DATE",
            'week'           => "s.submitted_at >= NOW() - INTERVAL '7 days'",
            'month'          => "s.submitted_at >= NOW() - INTERVAL '30 days'",
            'pending_review' => "s.status = 'submitted'",
            'approved'       => "s.status = 'approved'",
            'rejected'       => "s.status = 'rejected'",
            'draft'          => "s.status = 'draft'",
            default          => '1=1',
        };

        $where = implode(' AND ', $conditions);
        $join  = $filter['region_id'] ? 'JOIN hospitals h ON h.id = s.hospital_id' : '';

        $stmt = $db->prepare("SELECT COUNT(*) FROM submissions s $join WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function countHospitals(PDO $db, array $filter): int
    {
        $where  = $filter['region_id'] ? 'WHERE region_id = :rid' : '';
        $params = $filter['region_id'] ? ['rid' => $filter['region_id']] : [];
        $stmt   = $db->prepare("SELECT COUNT(*) FROM hospitals $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function countActiveHospitals(PDO $db, array $filter): int
    {
        $conditions = ["s.submitted_at >= NOW() - INTERVAL '30 days'"];
        $params     = [];
        if ($filter['region_id']) {
            $conditions[] = 'h.region_id = :rid';
            $params['rid'] = $filter['region_id'];
        }
        $where = implode(' AND ', $conditions);
        $stmt  = $db->prepare("SELECT COUNT(DISTINCT s.hospital_id) FROM submissions s JOIN hospitals h ON h.id = s.hospital_id WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function calculateReportingRate(PDO $db, array $filter): float
    {
        $total  = $this->countHospitals($db, $filter);
        $active = $this->countActiveHospitals($db, $filter);
        return $total > 0 ? round(($active / $total) * 100, 1) : 0;
    }

    private function getTopForms(PDO $db, array $filter): array
    {
        $stmt = $db->prepare("
            SELECT f.name, COUNT(s.id) AS submission_count
            FROM submissions s JOIN forms f ON f.id = s.form_id
            WHERE s.submitted_at >= NOW() - INTERVAL '30 days'
            GROUP BY f.name ORDER BY submission_count DESC LIMIT 5
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getSubmissionsByDay(PDO $db, array $filter, int $days): array
    {
        $stmt = $db->prepare("
            SELECT DATE(submitted_at) AS date, COUNT(*) AS count
            FROM submissions
            WHERE submitted_at >= NOW() - INTERVAL ':days days' AND status != 'draft'
            GROUP BY date ORDER BY date
        ");
        // Parameterize the interval safely
        $stmt = $db->prepare("
            SELECT DATE(submitted_at) AS day, COUNT(*) AS count
            FROM submissions
            WHERE submitted_at >= CURRENT_DATE - $days AND status != 'draft'
            GROUP BY day ORDER BY day
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getSubmissionsByForm(PDO $db, array $filter): array
    {
        $stmt = $db->prepare("
            SELECT f.name, f.category, COUNT(s.id) AS count
            FROM submissions s JOIN forms f ON f.id = s.form_id
            WHERE s.submitted_at >= NOW() - INTERVAL '30 days'
            GROUP BY f.name, f.category ORDER BY count DESC LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getHospitalRankings(PDO $db, array $filter): array
    {
        $where  = '';
        $params = [];
        if ($filter['region_id']) {
            $where = 'WHERE h.region_id = :rid';
            $params['rid'] = $filter['region_id'];
        }

        $stmt = $db->prepare("
            SELECT h.name, r.name AS region, COUNT(s.id) AS submissions,
                   MAX(s.submitted_at) AS last_submission,
                   ROUND(COUNT(s.id)::numeric / GREATEST(EXTRACT(DAY FROM NOW() - h.created_at), 1), 2) AS avg_per_day
            FROM hospitals h
            JOIN regions r ON r.id = h.region_id
            LEFT JOIN submissions s ON s.hospital_id = h.id AND s.submitted_at >= NOW() - INTERVAL '30 days'
            $where
            GROUP BY h.name, r.name, h.created_at
            ORDER BY submissions DESC LIMIT 20
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function getMissingReports(PDO $db, array $filter): array
    {
        // Hospitals that haven't submitted in the last 7 days
        $stmt = $db->prepare("
            SELECT h.name, h.code, r.name AS region, MAX(s.submitted_at) AS last_submission
            FROM hospitals h
            JOIN regions r ON r.id = h.region_id
            LEFT JOIN submissions s ON s.hospital_id = h.id
            WHERE h.is_active = TRUE
            GROUP BY h.name, h.code, r.name
            HAVING MAX(s.submitted_at) < NOW() - INTERVAL '7 days' OR MAX(s.submitted_at) IS NULL
            ORDER BY last_submission ASC NULLS FIRST LIMIT 20
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function buildScopeFilter(array $user): array
    {
        return [
            'hospital_id' => $user['role_name'] === 'data_entry' || $user['role_name'] === 'hospital_admin'
                                ? $user['hospital_id'] : null,
            'region_id'   => $user['role_name'] === 'regional_admin' ? $user['region_id'] : null,
        ];
    }

    private function getPeriodDates(string $period): array
    {
        $end = date('Y-m-d H:i:s');
        $start = match ($period) {
            'day'   => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week'  => date('Y-m-d H:i:s', strtotime('-7 days')),
            'month' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'year'  => date('Y-m-d H:i:s', strtotime('-365 days')),
            default => date('Y-m-d H:i:s', strtotime('-30 days')),
        };
        return [$start, $end];
    }
}
