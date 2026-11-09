<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'user_id',
        'type',
        'title',
        'body',
        'related_entity_type',
        'related_entity_id',
        'is_read',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'user_id' => 'required|is_natural_no_zero',
        'type'    => 'required|max_length[60]',
        'title'   => 'required|max_length[150]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function unreadFor(int $userId, int $limit = 20): array
    {
        return $this->where('user_id', $userId)
            ->where('is_read', 0)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->where('user_id', $userId)
            ->where('is_read', 0)
            ->countAllResults();
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        return $this->where('id', $notificationId)
            ->where('user_id', $userId)
            ->set(['is_read' => 1])
            ->update();
    }

    /**
     * Every notification of $userId, newest first, returned unexecuted so
     * the caller can paginate() it.
     */
    public function listQuery(int $userId): self
    {
        return $this->where('user_id', $userId)->orderBy('id', 'DESC');
    }

    public function markAllRead(int $userId): bool
    {
        return $this->where('user_id', $userId)
            ->where('is_read', 0)
            ->set(['is_read' => 1])
            ->update();
    }
}
