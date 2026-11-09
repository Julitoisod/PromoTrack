<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array> $notifications
 * @var int         $unreadCount
 */
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle"><?= esc((string) $unreadCount) ?> unread</p>
    </div>
    <?php if ($unreadCount > 0): ?>
        <form method="post" action="<?= esc(site_url('my/notifications/read-all')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all me-1"></i> Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<div class="card portal-card">
    <?php if ($notifications === []): ?>
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-bell-slash"></i>
                <p class="mb-0">No notifications yet.</p>
            </div>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush notification-list">
            <?php foreach ($notifications as $notification): ?>
                <li class="list-group-item <?= (int) $notification['is_read'] === 0 ? 'is-unread' : '' ?>">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold"><?= esc($notification['title']) ?></div>
                            <?php if (! empty($notification['body'])): ?>
                                <div class="text-muted small"><?= esc($notification['body']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small text-nowrap">
                            <?= esc(date('M j, Y g:i A', strtotime((string) $notification['created_at']))) ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?= $pager->links() ?>
<?= $this->endSection() ?>
