<?php
/**
 * The six semester folder cards. Shared by the admin dashboard and the
 * Student Reservist Enrollment page.
 *
 * @var array<string, array{level: string, count: int, pending: int}> $folders
 */
?>
<div class="row g-3 folder-grid">
    <?php foreach ($folders as $code => $folder): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a class="folder-card" href="<?= esc(site_url('admin/enrollment/' . $code)) ?>">
                <span class="folder-icon" aria-hidden="true"><i class="bi bi-folder-fill"></i></span>
                <span class="folder-name"><?= esc($code) ?></span>
                <span class="folder-count"><strong><?= esc((string) $folder['count']) ?></strong> Enrolled Student<?= $folder['count'] === 1 ? '' : 's' ?></span>
                <span class="folder-level"><?= esc($folder['level']) ?></span>
                <?php if ($folder['pending'] > 0): ?>
                    <span class="badge text-bg-warning folder-flag"><?= esc((string) $folder['pending']) ?> COR to verify</span>
                <?php endif; ?>
                <span class="folder-open">View Enrolled Students <i class="bi bi-arrow-right"></i></span>
            </a>
        </div>
    <?php endforeach; ?>
</div>
