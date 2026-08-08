<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationModel;

/**
 * Lightweight, DB-backed notifications (bell-icon list, polled on page
 * load). No websockets/queue -- nothing in the thesis requires
 * real-time push, and a polling read on an already-authenticated page
 * request is the smallest thing that works (YAGNI).
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationModel $notificationModel,
    ) {
    }

    public function notify(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null
    ): void {
        $this->notificationModel->insert([
            'user_id'             => $userId,
            'type'                => $type,
            'title'               => $title,
            'body'                => $body,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id'   => $relatedEntityId,
        ]);
    }

    /**
     * Notifies every user in Shield group $groupName. Reads
     * `auth_groups_users` directly since Shield groups are declarative
     * (defined in AuthGroups.php) rather than modeled by an App model.
     */
    public function notifyGroup(
        string $groupName,
        string $type,
        string $title,
        ?string $body = null,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null
    ): void {
        $userIds = db_connect()->table('auth_groups_users')
            ->select('user_id')
            ->where('group', $groupName)
            ->get()
            ->getResultArray();

        foreach ($userIds as $row) {
            $this->notify((int) $row['user_id'], $type, $title, $body, $relatedEntityType, $relatedEntityId);
        }
    }
}
