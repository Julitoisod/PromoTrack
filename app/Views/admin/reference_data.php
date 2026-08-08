<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * One screen for all four reference sets, rendered from the column map the
 * controller supplies. Rows are edited in place: each row is its own small
 * form, so a correction is one field and one button, and deactivation sits
 * next to it rather than behind a separate screen.
 *
 * Expects: $set, $sets, $config, $rows
 */
$base = site_url('admin/reference/' . $set);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?= esc($config['title']) ?></h1>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach ($sets as $slug => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $slug === $set ? 'active' : '' ?>" href="<?= esc(site_url('admin/reference/' . $slug)) ?>">
                <?= esc($label) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm mb-4">
    <div class="card-header">Add <?= esc(strtolower($config['singular'])) ?></div>
    <div class="card-body">
        <form method="post" action="<?= esc($base) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <?php foreach ($config['columns'] as $column => $meta): ?>
                <div class="col-md-<?= $meta['type'] === 'checkbox' ? '2' : '3' ?>">
                    <?php if ($meta['type'] === 'checkbox'): ?>
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1"
                                   id="new_<?= esc($column, 'attr') ?>" name="<?= esc($column, 'attr') ?>">
                            <label class="form-check-label" for="new_<?= esc($column, 'attr') ?>">
                                <?= esc($meta['label']) ?>
                            </label>
                        </div>
                    <?php else: ?>
                        <label class="form-label" for="new_<?= esc($column, 'attr') ?>"><?= esc($meta['label']) ?></label>
                        <input type="<?= esc($meta['type'], 'attr') ?>" class="form-control form-control-sm"
                               id="new_<?= esc($column, 'attr') ?>" name="<?= esc($column, 'attr') ?>"
                               value="<?= esc(old($column) ?? '') ?>"
                               <?= ($meta['required'] ?? false) ? 'required' : '' ?>>
                        <?= view('components/form_errors', ['field' => $column]) ?>
                        <?php if (isset($meta['help'])): ?>
                            <div class="form-text"><?= esc($meta['help']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><?= esc($config['title']) ?> on file</div>
    <div class="card-body">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <i class="bi bi-inboxes"></i>
                No <?= esc(strtolower($config['title'])) ?> have been recorded yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <?php foreach ($config['columns'] as $meta): ?>
                                <th><?= esc($meta['label']) ?></th>
                            <?php endforeach; ?>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $isActive = ((int) ($row['is_active'] ?? 0)) === 1; ?>
                            <tr class="<?= $isActive ? '' : 'text-muted' ?>">
                                <form method="post" action="<?= esc($base . '/' . $row['id']) ?>" id="edit-<?= esc($row['id'], 'attr') ?>">
                                    <?= csrf_field() ?>
                                </form>
                                <?php foreach ($config['columns'] as $column => $meta): ?>
                                    <td>
                                        <?php if ($meta['type'] === 'checkbox'): ?>
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   form="edit-<?= esc($row['id'], 'attr') ?>"
                                                   name="<?= esc($column, 'attr') ?>"
                                                   aria-label="<?= esc($meta['label'], 'attr') ?>"
                                                   <?= ((int) ($row[$column] ?? 0)) === 1 ? 'checked' : '' ?>>
                                        <?php else: ?>
                                            <input type="<?= esc($meta['type'], 'attr') ?>" class="form-control form-control-sm"
                                                   form="edit-<?= esc($row['id'], 'attr') ?>"
                                                   name="<?= esc($column, 'attr') ?>"
                                                   aria-label="<?= esc($meta['label'], 'attr') ?>"
                                                   value="<?= esc($row[$column] ?? '') ?>"
                                                   <?= ($meta['required'] ?? false) ? 'required' : '' ?>>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="submit" form="edit-<?= esc($row['id'], 'attr') ?>" class="btn btn-sm btn-outline-primary">
                                        Save
                                    </button>
                                    <form method="post" action="<?= esc($base . '/' . $row['id'] . '/toggle') ?>" class="d-inline mb-0">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                data-confirm="<?= $isActive ? 'Deactivate' : 'Reactivate' ?> this row?">
                                            <?= $isActive ? 'Deactivate' : 'Reactivate' ?>
                                        </button>
                                    </form>
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
