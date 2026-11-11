<?php
/**
 * The Standby and Ready Reserve folder cards. Shared by the admin dashboard
 * and the Reservist Classification page.
 *
 * @var array{standby: int, ready_reserve: int} $counts
 */
$classFolders = [
    'standby'       => ['Standby', 'View Standby Personnel', 'bi-shield'],
    'ready_reserve' => ['Ready Reserve', 'View Ready Reserve Personnel', 'bi-shield-fill-check'],
];
?>
<div class="row g-3">
    <?php foreach ($classFolders as $type => [$label, $cta, $icon]): ?>
        <div class="col-md-6">
            <a class="folder-card folder-card-wide folder-<?= esc($type) ?>" href="<?= esc(site_url('admin/classification/' . $type)) ?>">
                <span class="folder-icon" aria-hidden="true"><i class="bi bi-folder-fill"></i><i class="bi <?= esc($icon) ?> folder-badge-icon"></i></span>
                <span class="folder-text">
                    <span class="folder-name"><?= esc($label) ?></span>
                    <span class="folder-count"><strong><?= esc((string) $counts[$type]) ?></strong> <?= esc($label) ?> Reservist<?= $counts[$type] === 1 ? '' : 's' ?></span>
                    <span class="folder-open"><?= esc($cta) ?> <i class="bi bi-arrow-right"></i></span>
                </span>
            </a>
        </div>
    <?php endforeach; ?>
</div>
