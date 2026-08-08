<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLogModel;

/**
 * Entity-level, old/new-value audit logging for business operations
 * (package status transitions, order recording, etc.) -- complements,
 * does not replace, the request-level `AuditRequestFilter` that logs
 * every HTTP request's method/path/status regardless of what it did.
 *
 * Never throws: an audit-log failure must not break the operation it
 * is recording, mirroring `AuditRequestFilter`'s own try/catch.
 */
class AuditLogService
{
    public function __construct(
        private readonly AuditLogModel $auditLogModel,
    ) {
    }

    public function record(
        ?int $actorUserId,
        string $action,
        string $entityType,
        int $entityId,
        array $old = [],
        array $new = []
    ): void {
        try {
            $this->auditLogModel->insert([
                'user_id'     => $actorUserId,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'old_values'  => $old === [] ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
                'new_values'  => $new === [] ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
                'ip_address'  => service('request')->getIPAddress(),
                'user_agent'  => (string) service('request')->getUserAgent(),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'AuditLogService failed: ' . $exception->getMessage());
        }
    }
}
