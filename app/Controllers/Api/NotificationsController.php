<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Bell-icon notification list/mark-read endpoints, polled by the
 * navbar's dropdown on page load. See `App\Services\NotificationService`
 * for how notifications are created.
 */
class NotificationsController extends BaseController
{
    private NotificationModel $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
    }

    public function index(): ResponseInterface
    {
        $user = $this->currentUser();

        if ($user === null) {
            return $this->jsonError('Not authenticated.', [], 401);
        }

        $userId = (int) $user->id;

        return $this->jsonSuccess('OK', [
            'notifications' => $this->notificationModel->unreadFor($userId),
            'unread_count'  => $this->notificationModel->unreadCountFor($userId),
        ]);
    }

    public function markRead(?int $notificationId = null): ResponseInterface
    {
        $user = $this->currentUser();

        if ($user === null || $notificationId === null) {
            return $this->jsonError('Not authenticated.', [], 401);
        }

        $this->notificationModel->markRead($notificationId, (int) $user->id);

        return $this->jsonSuccess('Notification marked read.');
    }
}
