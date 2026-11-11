<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Changes the signed-in account's password, for the reservist's Settings and
 * the staff Account Settings alike. The current password is checked first,
 * and the new one goes through the same Shield password validators as
 * sign-up (length, nothing personal, not a common word).
 *
 * For controllers extending BaseController.
 */
trait ChangesPassword
{
    protected function changePasswordAndReturnTo(string $back): RedirectResponse
    {
        $user = $this->currentUser();

        $rules = [
            'current_password' => ['label' => 'Current Password', 'rules' => 'required'],
            'password'         => ['label' => 'New Password', 'rules' => 'required|max_byte[72]|strong_password[]'],
            'password_confirm' => ['label' => 'Repeat New Password', 'rules' => 'required|matches[password]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->to($back)
                ->with('errors', $this->validator->getErrors())
                ->with('error', 'Your password was not changed. Please check the fields below.');
        }

        $check = auth('session')->check([
            'email'    => $user->email,
            'password' => (string) $this->request->getPost('current_password'),
        ]);

        if (! $check->isOK()) {
            return redirect()->to($back)
                ->with('errors', ['current_password' => 'The current password is not correct.'])
                ->with('error', 'Your password was not changed.');
        }

        $user->fill(['password' => (string) $this->request->getPost('password')]);
        auth()->getProvider()->save($user);

        service('auditLogService')->record((int) $user->id, 'user.password_changed', 'user', (int) $user->id);

        return redirect()->to($back)->with('success', 'Your password has been changed.');
    }
}
