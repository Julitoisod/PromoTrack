<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Applies hardened HTTP security-response headers to every response.
 *
 * Content-Security-Policy is handled by CodeIgniter core
 * (`Config\App::$CSPEnabled` + `Config\ContentSecurityPolicy`) so it stays
 * compatible with Shield's nonce-based inline `<script>`/`<style>` tags.
 * This filter adds the remaining headers required by PromoTrack AFP-RF:
 * X-Frame-Options, X-Content-Type-Options, Referrer-Policy, and
 * Permissions-Policy.
 */
class SecurityHeadersFilter implements FilterInterface
{
    /**
     * @param array|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Nothing to do before the request is processed.
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Referrer-Policy', 'same-origin');
        $response->setHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );
    }
}
