<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var string      $type   standby | ready_reserve
 * @var list<array> $rows
 * @var int         $total
 * @var string      $returnTo
 */
$labels = App\Controllers\Admin\ReservistAdminController::CLASSIFICATIONS;
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><a href="<?= esc(site_url('/')) ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= esc(site_url('admin/classification')) ?>">Reservist Classification</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($labels[$type]) ?></li>
    </ol>
</nav>

<div class="page-head">
    <div class="d-flex align-items-center gap-3">
        <span class="folder-icon folder-icon-lg" aria-hidden="true"><i class="bi bi-folder2-open"></i></span>
        <div>
            <h1 class="page-title"><?= esc($labels[$type]) ?> Personnel</h1>
            <p class="page-subtitle"><strong><?= esc((string) $total) ?></strong> reservist<?= $total === 1 ? '' : 's' ?></p>
        </div>
    </div>
    <a href="<?= esc(site_url('admin/classification')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to folders</a>
</div>

<div class="card portal-card">
    <div class="card-body">
        <?= view('admin/_filters', ['filters' => $filters, 'filterCdcs' => $filterCdcs, 'filterCompanies' => $filterCompanies, 'action' => site_url('admin/classification/' . $type)]) ?>

        <?php if ($rows === []): ?>
            <div class="empty-state">
                <i class="bi bi-folder"></i>
                <p class="mb-0">No <?= esc(strtolower($labels[$type])) ?> personnel<?= $filters['q'] !== '' || $filters['cdc'] || $filters['company'] ? ' match the filters' : ' yet' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Reservist Name</th>
                            <th scope="col">Serial Number</th>
                            <th scope="col">Current Rank</th>
                            <th scope="col">CDC</th>
                            <th scope="col">Company</th>
                            <th scope="col">Date of Enlistment</th>
                            <th class="text-center" scope="col">Classification</th>
                            <th class="text-end" scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $assigned = $row['reserve_classification'] !== null; ?>
                            <tr>
                                <td class="fw-semibold"><?= esc(trim($row['last_name'] . ', ' . $row['first_name'])) ?></td>
                                <td><?= esc($row['afp_serial_number'] ?? '—') ?></td>
                                <td><?= esc($row['rank_name'] ?? '—') ?></td>
                                <td><?= esc($row['cdc_name'] ?? '—') ?></td>
                                <td><?= esc(preg_replace('/,.*$/', '', (string) ($row['company_name'] ?? '—'))) ?></td>
                                <td><?= esc($row['date_of_enlistment'] ?? '—') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $row['classification'] === 'ready_reserve' ? 'text-bg-success' : 'badge-standby' ?>"><?= esc($labels[$row['classification']] ?? '—') ?></span>
                                    <?php if (! $assigned): ?>
                                        <div class="small text-muted">self-reported</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= esc(site_url('reservists/' . $row['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View Profile"><i class="bi bi-person-vcard"></i></a>
                                    <form method="post" action="<?= esc(site_url('admin/classification/reservist/' . $row['id'])) ?>" class="d-inline-flex gap-1 align-items-center">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                                        <select class="form-select form-select-sm w-auto" name="classification" aria-label="Classification">
                                            <?php foreach ($labels as $value => $label): ?>
                                                <option value="<?= esc($value) ?>" <?= $row['classification'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary" title="Update Status">Update</button>
                                    </form>
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
