<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array> $rows
 * @var int         $total
 */
$labels = App\Controllers\Admin\ReservistAdminController::CLASSIFICATIONS;
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Regular Reservists</h1>
        <p class="page-subtitle"><strong><?= esc((string) $total) ?></strong> reservist<?= $total === 1 ? '' : 's' ?> who are not student reservists.</p>
    </div>
</div>

<div class="card portal-card">
    <div class="card-body">
        <?= view('admin/_filters', ['filters' => $filters, 'filterCdcs' => $filterCdcs, 'filterCompanies' => $filterCompanies, 'action' => site_url('admin/regular-reservists')]) ?>

        <?php if ($rows === []): ?>
            <div class="empty-state"><i class="bi bi-people"></i><p class="mb-0">No regular reservists found.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Full Name</th>
                            <th scope="col">Serial Number</th>
                            <th scope="col">Current Rank</th>
                            <th scope="col">CDC</th>
                            <th scope="col">Company</th>
                            <th class="text-center" scope="col">Classification</th>
                            <th class="text-center" scope="col">Promotion</th>
                            <th class="text-end" scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc(trim($row['last_name'] . ', ' . $row['first_name'])) ?></td>
                                <td><?= esc($row['afp_serial_number'] ?? '—') ?></td>
                                <td><?= esc($row['rank_name'] ?? '—') ?></td>
                                <td><?= esc($row['cdc_name'] ?? '—') ?></td>
                                <td><?= esc(preg_replace('/,.*$/', '', (string) ($row['company_name'] ?? '—'))) ?></td>
                                <td class="text-center">
                                    <?php if (isset($labels[$row['classification'] ?? ''])): ?>
                                        <span class="badge <?= $row['classification'] === 'ready_reserve' ? 'text-bg-success' : 'badge-standby' ?>"><?= esc($labels[$row['classification']]) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= view('components/promotion_status', ['status' => $row['promotion_status'] ?? null]) ?></td>
                                <td class="text-end">
                                    <a href="<?= esc(site_url('reservists/' . $row['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View Profile"><i class="bi bi-person-vcard"></i></a>
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
