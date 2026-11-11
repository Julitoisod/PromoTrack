<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ChangesPassword;
use App\Models\NotificationModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * The reservist's Notifications and Settings screens from the side menu.
 */
class MyAccountController extends ReservistPortalController
{
    use ChangesPassword;

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

    public function changePassword(): RedirectResponse
    {
        return $this->changePasswordAndReturnTo(site_url('my/settings'));
    }
}
