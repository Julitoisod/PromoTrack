<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var string      $code
 * @var string      $level
 * @var list<array> $students
 * @var array<int, array> $enrollments  reservist id => current semester's COR row
 * @var int         $total
 * @var string      $returnTo
 */
$pageStart = (($pager->getCurrentPage() ?? 1) - 1) * 20;
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-2">
        <li class="breadcrumb-item"><a href="<?= esc(site_url('/')) ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= esc(site_url('admin/enrollment')) ?>">Student Reservists</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= esc($code) ?></li>
    </ol>
</nav>

<div class="page-head">
    <div class="d-flex align-items-center gap-3">
        <span class="folder-icon folder-icon-lg" aria-hidden="true"><i class="bi bi-folder2-open"></i></span>
        <div>
            <h1 class="page-title"><?= esc($code) ?> Enrolled Students</h1>
            <p class="page-subtitle"><?= esc($level) ?> &middot; <strong><?= esc((string) $total) ?></strong> enrolled student<?= $total === 1 ? '' : 's' ?></p>
        </div>
    </div>
    <a href="<?= esc(site_url('admin/enrollment')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to folders</a>
</div>

<div class="card portal-card">
    <div class="card-body">
        <?= view('admin/_filters', ['filters' => $filters, 'filterCdcs' => $filterCdcs, 'filterCompanies' => $filterCompanies, 'action' => site_url('admin/enrollment/' . $code)]) ?>

        <?php if ($students === []): ?>
            <div class="empty-state">
                <i class="bi bi-folder"></i>
                <p class="mb-0">No students in this folder<?= $filters['q'] !== '' || $filters['cdc'] || $filters['company'] ? ' match the filters' : ' yet' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Serial Number</th>
                            <th scope="col">CDC</th>
                            <th scope="col">Company</th>
                            <th scope="col">Academic Year / Semester</th>
                            <th class="text-center" scope="col">COR Status</th>
                            <th class="text-center" scope="col">Enrollment Status</th>
                            <th class="text-end" scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $student): ?>
                            <?php
                            $cor      = $enrollments[(int) $student['id']] ?? null;
                            $corState = $cor === null || $cor['cor_path'] === null
                                ? ['Not uploaded', 'secondary']
                                : (App\Models\StudentEnrollmentModel::STATUSES[$cor['status']] ?? ['Pending', 'warning']);
                            $enrolled = ($cor['status'] ?? null) === 'verified';
                            $name     = trim($student['last_name'] . ', ' . $student['first_name']
                                . (! empty($student['middle_name']) ? ' ' . mb_substr($student['middle_name'], 0, 1) . '.' : ''));
                            ?>
                            <tr>
                                <td><?= esc((string) ($pageStart + $i + 1)) ?></td>
                                <td class="fw-semibold"><?= esc($name) ?></td>
                                <td><?= esc($student['afp_serial_number'] ?? '—') ?></td>
                                <td><?= esc($student['cdc_name'] ?? '—') ?></td>
                                <td><?= esc(preg_replace('/,.*$/', '', (string) ($student['company_name'] ?? '—'))) ?></td>
                                <td><?= esc($level) ?></td>
                                <td class="text-center"><span class="badge text-bg-<?= esc($corState[1]) ?>"><?= esc($corState[0]) ?></span></td>
                                <td class="text-center">
                                    <span class="badge text-bg-<?= $enrolled ? 'success' : 'warning' ?>"><?= $enrolled ? 'Enrolled' : 'Pending' ?></span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= esc(site_url('reservists/' . $student['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View Profile">
                                        <i class="bi bi-person-vcard"></i>
                                    </a>
                                    <?php if ($cor !== null && $cor['cor_path'] !== null): ?>
                                        <a href="<?= esc(site_url('admin/enrollment/cor/' . $cor['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View Uploaded COR" target="_blank" rel="noopener">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </a>
                                        <?php if ($cor['status'] !== 'verified'): ?>
                                            <form method="post" action="<?= esc(site_url('admin/enrollment/cor/' . $cor['id'] . '/verify')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Verify Enrollment"><i class="bi bi-check-lg me-1"></i>Verify</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($cor['status'] === 'pending'): ?>
                                            <form method="post" action="<?= esc(site_url('admin/enrollment/cor/' . $cor['id'] . '/reject')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= esc($returnTo) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject COR" data-confirm="Reject this COR and ask the student to upload it again?"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        <?php endif; ?>
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
