<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * The reservist's Notifications and Settings screens from the side menu.
 */
class MyAccountController extends ReservistPortalController
{
    public function notifications(): string
    {
        $user  = $this->currentUser();
        $model = new NotificationModel();

        return view('my/notifications', [
            'title'         => 'Notifications',
            'notifications' => $model->listQuery((int) $user->id)->paginate(20),
            'pager'         => $model->pager,
            'unreadCount'   => (new NotificationModel())->unreadCountFor((int) $user->id),
        ]);
    }

    public function markAllRead(): RedirectResponse
    {
        (new NotificationModel())->markAllRead((int) $this->currentUser()->id);

        return redirect()->to(site_url('my/notifications'))->with('success', 'All notifications are marked as read.');
    }

    public function settings(): string
    {
        return view('my/settings', [
            'title' => 'Settings',
            'user'  => $this->currentUser(),
        ]);
    }

    /**
     * Changes the signed-in account's password. The current password is
     * checked first, and the new one goes through the same Shield password
     * validators as sign-up (length, nothing personal, not a common word).
     */
    public function changePassword(): RedirectResponse
    {
        $user = $this->currentUser();

        $rules = [
            'current_password' => ['label' => 'Current Password', 'rules' => 'required'],
            'password'         => ['label' => 'New Password', 'rules' => 'required|max_byte[72]|strong_password[]'],
            'password_confirm' => ['label' => 'Repeat New Password', 'rules' => 'required|matches[password]'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(site_url('my/settings'))
                ->with('errors', $this->validator->getErrors())
                ->with('error', 'Your password was not changed. Please check the fields below.');
        }

        $check = auth('session')->check([
            'email'    => $user->email,
            'password' => (string) $this->request->getPost('current_password'),
        ]);

        if (! $check->isOK()) {
            return redirect()->to(site_url('my/settings'))
                ->with('errors', ['current_password' => 'The current password is not correct.'])
                ->with('error', 'Your password was not changed.');
        }

        $user->fill(['password' => (string) $this->request->getPost('password')]);
        auth()->getProvider()->save($user);

        service('auditLogService')->record((int) $user->id, 'user.password_changed', 'user', (int) $user->id);

        return redirect()->to(site_url('my/settings'))->with('success', 'Your password has been changed.');
    }
}
