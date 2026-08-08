<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * Expects: $editable, $values, $backups, $canBackup
 */
$formatSize = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    return number_format($bytes / 1024, 0) . ' KB';
};
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">System Settings</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">Application</div>
    <div class="card-body">
        <form method="post" action="<?= esc(site_url('admin/settings')) ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php foreach ($editable as $key => $meta): ?>
                <?php $field = str_replace('.', '_', $key); ?>
                <div class="col-md-6">
                    <label class="form-label" for="<?= esc($field, 'attr') ?>"><?= esc($meta['label']) ?></label>
                    <input type="<?= esc($meta['type'], 'attr') ?>" class="form-control form-control-sm"
                           id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>"
                           value="<?= esc(old($field) ?? $values[$key]) ?>">
                    <div class="form-text"><?= esc($meta['help']) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Save settings
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Database Backups</span>
        <?php if ($canBackup): ?>
            <form method="post" action="<?= esc(site_url('admin/backups')) ?>" class="mb-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-database-down me-1"></i> Create backup now
                </button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Dumps are written to <code>writable/backups</code>, outside the web root. Restoring is
            deliberately not a button here — it overwrites live data, so it is run from a shell on the
            server:
            <code>mysql --host=… --user=… -p promotrack_afprf &lt; <em>backup-file</em>.sql</code>
        </p>

        <?php if ($backups === []): ?>
            <div class="empty-state">
                <i class="bi bi-hdd-stack"></i>
                No backups have been taken yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Taken</th>
                            <th>Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><code><?= esc($backup['filename']) ?></code></td>
                                <td><?= esc($backup['created_at']) ?></td>
                                <td class="text-nowrap"><?= esc($formatSize($backup['size_bytes'])) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if ($canBackup): ?>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= esc(site_url('admin/backups/' . $backup['filename'] . '/download')) ?>">
                                            Download
                                        </a>
                                        <form method="post" action="<?= esc(site_url('admin/backups/' . $backup['filename'] . '/delete')) ?>" class="d-inline mb-0">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    data-confirm="Delete this backup permanently?">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
