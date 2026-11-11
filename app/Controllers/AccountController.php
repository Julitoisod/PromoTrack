<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ChangesPassword;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Account Settings for staff accounts (administrator, clerk, CDC, RCDG,
 * records, commander): their own username and email -- shown masked with a
 * Show/Hide control, like a password -- and a password change.
 *
 * Every signed-in staff account may open it: it only ever touches the
 * account in the session, never one named in the URL. A reservist is sent to
 * their own Settings in the portal instead.
 */
class AccountController extends BaseController
{
    use ChangesPassword;

    /**
     * @return RedirectResponse|string
     */
    public function settings()
    {
        if ($this->isReservistAccount()) {
            return redirect()->to(site_url('my/settings'));
        }

        $user   = $this->currentUser();
        $groups = config('AuthGroups')->groups;

        return view('account/settings', [
            'title' => 'Account Settings',
            'user'  => $user,
            'roles' => array_map(static fn (string $g): string => $groups[$g]['title'] ?? $g, $user->getGroups()),
        ]);
    }

    public function changePassword(): RedirectResponse
    {
        if ($this->isReservistAccount()) {
            return redirect()->to(site_url('my/settings'));
        }

        return $this->changePasswordAndReturnTo(site_url('account/settings'));
    }
}
