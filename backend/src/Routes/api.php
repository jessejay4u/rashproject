<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\SubmissionController;
use App\Controllers\FormController;
use App\Controllers\DashboardController;
use App\Controllers\HospitalController;
use App\Controllers\UserController;
use App\Controllers\ReportController;
use App\Controllers\AuditController;
use App\Middleware\RateLimitMiddleware;
use App\Helpers\Response;

/**
 * Route the incoming request to the correct controller action.
 * Pattern: METHOD /api/resource[/{id}][/{action}]
 */
function route(string $method, string $path): void
{
    $rateLimit = new RateLimitMiddleware();
    $rateLimit->handle();

    $segments = array_values(array_filter(explode('/', ltrim($path, '/'))));
    // segments[0] = 'api', segments[1] = resource, segments[2] = id, segments[3] = sub-action

    $resource  = $segments[1] ?? '';
    $id        = $segments[2] ?? null;
    $subAction = $segments[3] ?? null;

    match (true) {
        // ── Auth ──────────────────────────────────────────────────
        $resource === 'auth' && $id === 'login'           && $method === 'POST' => (new AuthController())->login(),
        $resource === 'auth' && $id === 'refresh'         && $method === 'POST' => (new AuthController())->refresh(),
        $resource === 'auth' && $id === 'logout'          && $method === 'POST' => (new AuthController())->logout(),
        $resource === 'auth' && $id === 'me'              && $method === 'GET'  => (new AuthController())->me(),
        $resource === 'auth' && $id === 'change-password' && $method === 'POST' => (new AuthController())->changePassword(),
        $resource === 'auth' && $id === 'forgot-password' && $method === 'POST' => (new AuthController())->forgotPassword(),
        $resource === 'auth' && $id === 'reset-password'  && $method === 'POST' => (new AuthController())->resetPassword(),

        // ── Submissions ───────────────────────────────────────────
        $resource === 'submissions' && $method === 'GET'  && $id === null                          => (new SubmissionController())->index(),
        $resource === 'submissions' && $method === 'POST' && $id === null                          => (new SubmissionController())->store(),
        $resource === 'submissions' && $method === 'GET'  && $id !== null && $subAction === null   => (new SubmissionController())->show($id),
        $resource === 'submissions' && $method === 'POST' && $subAction === 'review'               => (new SubmissionController())->review($id),
        $resource === 'submissions' && $id === 'sync'     && $method === 'POST'                    => (new SubmissionController())->syncBatch(),

        // ── Forms ─────────────────────────────────────────────────
        $resource === 'forms' && $method === 'GET'  && $id === null                        => (new FormController())->index(),
        $resource === 'forms' && $method === 'POST' && $id === null                        => (new FormController())->store(),
        $resource === 'forms' && $method === 'GET'  && $id !== null && $subAction === null => (new FormController())->show($id),
        $resource === 'forms' && $method === 'POST' && $subAction === 'publish'            => (new FormController())->publish($id),
        $resource === 'forms' && $method === 'POST' && $subAction === 'archive'            => (new FormController())->archive($id),

        // ── Dashboard ─────────────────────────────────────────────
        $resource === 'dashboard' && $id === 'summary' && $method === 'GET' => (new DashboardController())->summary(),
        $resource === 'dashboard' && $id === 'kpis'    && $method === 'GET' => (new DashboardController())->kpis(),
        $resource === 'dashboard' && $id === 'map'     && $method === 'GET' => (new DashboardController())->map(),
        $resource === 'dashboard' && $id === 'trends'  && $method === 'GET' => (new DashboardController())->trends(),

        // ── Hospitals ─────────────────────────────────────────────
        $resource === 'hospitals' && $method === 'GET'  && $id === null   => (new HospitalController())->index(),
        $resource === 'hospitals' && $method === 'POST' && $id === null   => (new HospitalController())->store(),
        $resource === 'hospitals' && $method === 'GET'  && $id !== null   => (new HospitalController())->show($id),
        $resource === 'hospitals' && $method === 'PUT'  && $id !== null   => (new HospitalController())->update($id),
        $resource === 'hospitals' && $method === 'PATCH'&& $id !== null   => (new HospitalController())->update($id),

        // ── Users ─────────────────────────────────────────────────
        $resource === 'users' && $method === 'GET'    && $id === null             => (new UserController())->index(),
        $resource === 'users' && $method === 'POST'   && $id === null             => (new UserController())->store(),
        $resource === 'users' && $method === 'GET'    && $id !== null             => (new UserController())->show($id),
        $resource === 'users' && $method === 'PUT'    && $id !== null             => (new UserController())->update($id),
        $resource === 'users' && $method === 'PATCH'  && $id !== null             => (new UserController())->update($id),
        $resource === 'users' && $method === 'DELETE' && $id !== null             => (new UserController())->deactivate($id),

        // ── Reports ───────────────────────────────────────────────
        $resource === 'reports' && $method === 'GET'  && $id === null             => (new ReportController())->index(),
        $resource === 'reports' && $method === 'POST' && $id === null             => (new ReportController())->generate(),
        $resource === 'reports' && $method === 'GET'  && $id !== null && $subAction === 'download' => (new ReportController())->download($id),
        $resource === 'reports' && $id === 'export'   && $method === 'GET'        => (new ReportController())->exportSubmissions(),

        // ── Audit Logs ────────────────────────────────────────────
        $resource === 'audit' && $method === 'GET' => (new AuditController())->index(),

        // ── Health check ──────────────────────────────────────────
        $resource === 'health' && $method === 'GET' => Response::success(['status' => 'ok', 'timestamp' => date('c')]),

        // ── 404 ───────────────────────────────────────────────────
        default => Response::notFound("Route not found: $method /api/$resource"),
    };
}
