<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Records a lightweight access-log entry for every request into the
 * `audit_logs` table (IP address, user agent, route, HTTP method, and
 * response status).
 *
 * This is a baseline, request-level audit trail distinct from the
 * detailed, entity-level change auditing performed by the future
 * `AuditLogService` (which records old/new values for business
 * operations such as package status transitions). Writes directly to
 * the database so it has no dependency on the Service layer.
 */
class AuditRequestFilter implements FilterInterface
{
    /**
     * @param array|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Nothing required before the request.
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        try {
            $userId = auth()->loggedIn() ? auth()->user()->id : null;

            $payload = json_encode([
                'path'   => $request->getUri()->getPath(),
                'status' => $response->getStatusCode(),
            ], JSON_UNESCAPED_SLASHES);

            db_connect()->table('audit_logs')->insert([
                'user_id'     => $userId,
                'action'      => $request->getMethod(),
                'entity_type' => 'http_request',
                'entity_id'   => null,
                'old_values'  => null,
                'new_values'  => $payload,
                'ip_address'  => $request->getIPAddress(),
                'user_agent'  => (string) $request->getUserAgent(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            // Never let audit logging break the response cycle.
            log_message('error', 'AuditRequestFilter failed: ' . $exception->getMessage());
        }
    }
}
