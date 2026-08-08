<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * Read-only audit trail. Paginated in SQL, not in the browser: this is the
 * fastest-growing table in the system.
 *
 * Expects: $rows, $pager, $filters, $actions, $entityTypes
 */
$exportQuery = http_build_query(array_filter($filters, static fn ($value): bool => $value !== ''));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Audit Logs</h1>
        <p class="text-muted mb-0">Append-only record of who changed what, and when.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= esc(site_url('admin/audit-logs/export') . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>">
        <i class="bi bi-filetype-csv me-1"></i> Export CSV
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= esc(site_url('admin/audit-logs')) ?>" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="action">Action</label>
                <select class="form-select form-select-sm" id="action" name="action">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= esc($action, 'attr') ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>>
                            <?= esc($action) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="entity_type">Entity</label>
                <select class="form-select form-select-sm" id="entity_type" name="entity_type">
                    <option value="">All entities</option>
                    <?php foreach ($entityTypes as $entityType): ?>
                        <option value="<?= esc($entityType, 'attr') ?>" <?= $filters['entity_type'] === $entityType ? 'selected' : '' ?>>
                            <?= esc($entityType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_from">From</label>
                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                       value="<?= esc($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_to">To</label>
                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                       value="<?= esc($filters['date_to']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="q">Search</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q"
                       placeholder="user, entity, value" value="<?= esc($filters['q']) ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <i class="bi bi-journal-text"></i>
                No audit entries match this filter.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>IP</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="text-nowrap"><?= esc($row['created_at']) ?></td>
                                <td><?= esc($row['username'] ?? 'system') ?></td>
                                <td><span class="badge text-bg-secondary"><?= esc($row['action']) ?></span></td>
                                <td>
                                    <?= esc($row['entity_type'] ?? '—') ?>
                                    <?= $row['entity_id'] !== null ? '#' . esc((string) $row['entity_id']) : '' ?>
                                </td>
                                <td><?= esc($row['ip_address'] ?? '—') ?></td>
                                <td>
                                    <?php if (($row['old_values'] ?? null) !== null || ($row['new_values'] ?? null) !== null): ?>
                                        <details>
                                            <summary class="small text-primary">View</summary>
                                            <?php if (($row['old_values'] ?? null) !== null): ?>
                                                <div class="small text-muted mt-1">Before</div>
                                                <pre class="small mb-1"><?= esc($row['old_values']) ?></pre>
                                            <?php endif; ?>
                                            <?php if (($row['new_values'] ?? null) !== null): ?>
                                                <div class="small text-muted">After</div>
                                                <pre class="small mb-0"><?= esc($row['new_values']) ?></pre>
                                            <?php endif; ?>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $pager->links() ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
