<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var list<array> $rows
 * @var int         $total
 */
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Registrations</h1>
        <p class="page-subtitle"><strong><?= esc((string) $total) ?></strong> reservist account<?= $total === 1 ? '' : 's' ?> created through Sign Up, newest first.</p>
    </div>
</div>

<div class="card portal-card">
    <div class="card-body">
        <?= view('admin/_filters', ['filters' => $filters, 'filterCdcs' => $filterCdcs, 'filterCompanies' => $filterCompanies, 'action' => site_url('admin/registrations')]) ?>

        <?php if ($rows === []): ?>
            <div class="empty-state"><i class="bi bi-person-plus"></i><p class="mb-0">No registrations found.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Full Name</th>
                            <th scope="col">Serial Number</th>
                            <th scope="col">CDC</th>
                            <th scope="col">Company</th>
                            <th scope="col">Rank</th>
                            <th class="text-center" scope="col">Type</th>
                            <th scope="col">Registered</th>
                            <th class="text-end" scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $student = (int) $row['is_student'] === 1 && $row['educational_attainment'] === 'undergraduate'; ?>
                            <tr>
                                <td class="fw-semibold"><?= esc(trim($row['last_name'] . ', ' . $row['first_name'])) ?></td>
                                <td><?= esc($row['afp_serial_number'] ?? '—') ?></td>
                                <td><?= esc($row['cdc_name'] ?? '—') ?></td>
                                <td><?= esc(preg_replace('/,.*$/', '', (string) ($row['company_name'] ?? '—'))) ?></td>
                                <td><?= esc($row['rank_name'] ?? '—') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $student ? 'text-bg-info' : 'text-bg-secondary' ?>"><?= $student ? 'Student' : 'Regular' ?></span>
                                </td>
                                <td><?= esc($row['created_at'] !== null ? date('M j, Y', strtotime((string) $row['created_at'])) : '—') ?></td>
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
