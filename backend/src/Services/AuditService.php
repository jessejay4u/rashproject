<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;

class AuditService
{
    public function log(
        ?string $userId,
        string  $action,
        ?string $entityType = null,
        ?string $entityId   = null,
        ?array  $oldValues  = null,
        ?array  $newValues  = null,
        ?string $ip         = null,
        array   $metadata   = []
    ): void {
        $ip        = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            Database::connection()->prepare(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, metadata)
                 VALUES (:uid, :action, :etype, :eid, :old, :new, :ip, :ua, :meta)'
            )->execute([
                'uid'    => $userId,
                'action' => $action,
                'etype'  => $entityType,
                'eid'    => $entityId,
                'old'    => $oldValues !== null ? json_encode($oldValues) : null,
                'new'    => $newValues !== null ? json_encode($newValues) : null,
                'ip'     => $ip,
                'ua'     => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'meta'   => json_encode($metadata),
            ]);
        } catch (\Exception $e) {
            // Audit failures must never break the main flow — log to file
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
