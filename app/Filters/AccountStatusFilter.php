<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks access for users whose accounts have been administratively
 * deactivated (see `users.deactivated_at` / `users.deactivation_reason`).
 *
 * This is independent from CodeIgniter Shield's own banned/activation
 * checks (handled by the `session` filter); it enforces PromoTrack's
 * own account-deactivation workflow performed by administrators.
 *
 * Must be used together with the `session` filter on protected routes.
 */
class AccountStatusFilter implements FilterInterface
{
    /**
     * @param array|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return;
        }

        $user = auth()->user();

        if ($user !== null && $user->deactivated_at !== null) {
            $reason = $user->deactivation_reason ?? lang('Auth.logOutBannedUser');

            auth()->logout();

            return redirect()->route('login')->with('error', $reason);
        }
    }

    /**
     * @param array|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // Nothing required after the request.
    }
}
