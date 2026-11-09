<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $showUser = auth()->user(); ?>
<div class="page-head">
    <div class="d-flex align-items-center gap-3">
        <?php if ($reservist->profile_photo !== null): ?>
            <img src="<?= esc(site_url('reservists/' . $reservist->id . '/photo')) ?>" alt="" class="staff-avatar" width="56" height="56">
        <?php endif; ?>
        <h1 class="page-title"><?= esc($reservist->getFullName()) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <?php if ($showUser !== null && $showUser->can('reservists.update')): ?>
            <a href="<?= esc(site_url('reservists/' . $reservist->id . '/edit')) ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        <?php endif; ?>
        <a href="<?= esc(site_url('reservists')) ?>" class="btn btn-outline-secondary">Back to list</a>
    </div>
</div>

<?php
$statusBadge = match ($reservist->status) {
    'active'     => 'success',
    'inactive'   => 'secondary',
    'discharged' => 'warning',
    'deceased'   => 'dark',
    default      => 'secondary',
};
?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Personal Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Reservist Number</dt>
                    <dd class="col-sm-7"><?= esc($reservist->reservist_number) ?></dd>

                    <dt class="col-sm-5">AFP Serial Number</dt>
                    <dd class="col-sm-7"><?= esc($reservist->afp_serial_number ?? '—') ?></dd>

                    <dt class="col-sm-5">Sex</dt>
                    <dd class="col-sm-7"><?= esc($reservist->sex !== null ? ucfirst($reservist->sex) : '—') ?></dd>

                    <dt class="col-sm-5">Date of Birth</dt>
                    <dd class="col-sm-7"><?= esc($reservist->date_of_birth?->toDateString() ?? '—') ?></dd>

                    <dt class="col-sm-5">Contact Number</dt>
                    <dd class="col-sm-7"><?= esc($reservist->contact_number ?? '—') ?></dd>

                    <dt class="col-sm-5">Email</dt>
                    <dd class="col-sm-7"><?= esc($reservist->email ?? '—') ?></dd>

                    <dt class="col-sm-5">Address</dt>
                    <dd class="col-sm-7"><?= esc($reservist->address ?? '—') ?></dd>

                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7"><span class="badge text-bg-<?= esc($statusBadge) ?>"><?= esc(ucfirst($reservist->status ?? 'active')) ?></span></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Service Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Current Rank</dt>
                    <dd class="col-sm-7"><?= esc($rank['rank_name'] ?? '—') ?></dd>

                    <dt class="col-sm-5">Organizational Unit</dt>
                    <dd class="col-sm-7"><?= esc($unit['unit_name'] ?? '—') ?></dd>

                    <dt class="col-sm-5">Company</dt>
                    <dd class="col-sm-7"><?= esc($company['unit_name'] ?? '—') ?></dd>

                    <dt class="col-sm-5">Education Track</dt>
                    <dd class="col-sm-7"><?= esc($educationTrack['track_name'] ?? '—') ?></dd>

                    <dt class="col-sm-5">Educational Attainment</dt>
                    <dd class="col-sm-7"><?= esc($reservist->getAttainmentLabel() ?? '—') ?></dd>

                    <dt class="col-sm-5">Schooling</dt>
                    <dd class="col-sm-7"><?= esc($reservist->getSchoolingLabel()) ?></dd>

                    <dt class="col-sm-5">Promotion Status</dt>
                    <dd class="col-sm-7"><?= view('components/promotion_status', ['status' => $promotionStatus]) ?></dd>

                    <dt class="col-sm-5">Date of Enlistment</dt>
                    <dd class="col-sm-7"><?= esc($reservist->date_of_enlistment?->toDateString() ?? '—') ?></dd>

                    <dt class="col-sm-5">Date of Last Promotion</dt>
                    <dd class="col-sm-7"><?= esc($reservist->date_of_last_promotion?->toDateString() ?? '—') ?></dd>

                    <dt class="col-sm-5">Time in Grade</dt>
                    <dd class="col-sm-7">
                        <?php $tigMonths = $reservist->getTimeInGradeMonths(); ?>
                        <?= $tigMonths !== null ? esc((string) intdiv($tigMonths, 12)) . ' yr(s), ' . esc((string) ($tigMonths % 12)) . ' mo(s)' : '—' ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <?php if ($rotc !== null): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>ROTC Progress <span class="text-muted fw-normal small">(reported by the student)</span></span>
                    <span class="badge text-bg-<?= esc($rotc['status']['tone']) ?>"><?= esc($rotc['status']['title']) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-3">Current Level</dt>
                        <dd class="col-sm-9"><?= esc($rotc['currentLabel'] . ' · ' . $rotc['currentLevel']) ?></dd>
                        <dt class="col-sm-3">Cadet Rank</dt>
                        <dd class="col-sm-9"><?= $rotc['rankHidden'] ? 'Not shown (training incomplete)' : esc($rotc['cadetRank'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Reserve Rank Earned</dt>
                        <dd class="col-sm-9"><?= $rotc['rankHidden'] ? '—' : esc($rotc['reserveRank'] ?? '—') ?></dd>
                        <dt class="col-sm-3">Classification</dt>
                        <dd class="col-sm-9"><?= esc($rotc['classification'] ?? '—') ?></dd>
                    </dl>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Stage</th><th>Semester</th><th>State</th><th>Answers</th></tr></thead>
                            <tbody>
                                <?php foreach ($rotc['stages'] as $code => $stage): ?>
                                    <?php
                                    $stageRow = $stage['row'];
                                    $answers  = array_filter([
                                        $stageRow['training_result'] ?? null,
                                        isset($stageRow['recommendation']) ? str_replace('_', ' ', $stageRow['recommendation']) : null,
                                        isset($stageRow['classification']) ? str_replace('_', ' ', $stageRow['classification']) : null,
                                        $stageRow['pose_rank'] ?? null,
                                        ($stageRow['checks'] ?? []) !== [] ? implode(', ', $stageRow['checks']) : null,
                                    ]);
                                    ?>
                                    <tr>
                                        <td><?= esc($stage['label']) ?></td>
                                        <td><?= esc($stage['config']['level']) ?></td>
                                        <td><?= esc(ucfirst($stage['state'])) ?></td>
                                        <td>
                                            <?= $answers !== [] ? esc(implode(' · ', $answers)) : '<span class="text-muted">—</span>' ?>
                                            <?php if ($code === 'E4' && ($stageRow['justification_path'] ?? null) !== null): ?>
                                                <a href="<?= esc(site_url('reservists/' . $reservist->id . '/rotc-justification')) ?>" class="ms-2">
                                                    <i class="bi bi-paperclip"></i> Justification letter
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?= view('reservists/_records', [
        'reservist'     => $reservist,
        'trainings'     => $trainings,
        'fitnessTests'  => $fitnessTests,
        'eligibilities' => $eligibilities,
        'rankHistory'   => $rankHistory,
        'trainingTypes' => $trainingTypes,
        'ranks'         => $ranks,
        'canEdit'       => $showUser !== null && $showUser->can('reservists.update'),
    ]) ?>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Promotion Eligibility</span>
                <div class="d-flex gap-2">
                    <?php if ($showUser !== null && $showUser->can('assessments.compute')): ?>
                        <form method="post" action="<?= esc(site_url('reservists/' . $reservist->id . '/assessments')) ?>" class="mb-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-calculator me-1"></i> Run Eligibility Assessment
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($showUser !== null && $showUser->can('packages.create')): ?>
                        <form method="post" action="<?= esc(site_url('reservists/' . $reservist->id . '/packages')) ?>" class="mb-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-folder-plus me-1"></i> Create Promotion Package
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($assessments)): ?>
                    <div class="empty-state"><i class="bi bi-clipboard-check"></i><p class="mb-0">No eligibility assessment has been run for this reservist yet.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">As of</th>
                                    <th scope="col">Time in Grade</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Manual Review?</th>
                                    <th scope="col">Verified</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assessments as $assessment): ?>
                                    <?php
                                    $statusBadgeClass = match ($assessment['promotion_status']) {
                                        'overdue'          => 'danger',
                                        'eligible'          => 'success',
                                        'not_yet_eligible' => 'secondary',
                                        default            => 'warning',
                                    };
                                    $statusLabel = $assessment['promotion_status'] !== null
                                        ? ucwords(str_replace('_', ' ', $assessment['promotion_status']))
                                        : 'Unverified';
                                    ?>
                                    <tr>
                                        <td><?= esc($assessment['as_of_date']) ?></td>
                                        <?php $timeInGrade = App\Models\PromotionAssessmentModel::timeInGradeCriterion($assessment); ?>
                                        <td>
                                            <?= $assessment['time_in_grade_months'] !== null ? esc((string) $assessment['time_in_grade_months']) . ' mo(s)' : '—' ?>
                                            <?php if (($timeInGrade['remaining_months'] ?? null) !== null): ?>
                                                <div class="small text-muted"><?= esc((string) $timeInGrade['remaining_months']) ?> mo(s) to go</div>
                                            <?php elseif (($timeInGrade['overdue_months'] ?? null) !== null): ?>
                                                <div class="small text-muted"><?= esc((string) $timeInGrade['overdue_months']) ?> mo(s) overdue</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge text-bg-<?= esc($statusBadgeClass) ?>"><?= esc($statusLabel) ?></span></td>
                                        <td><?= ((int) $assessment['requires_manual_review']) === 1 ? 'Yes' : 'No' ?></td>
                                        <td>
                                            <?php if ($assessment['verified_at'] !== null): ?>
                                                <span class="badge text-bg-success">Verified</span>
                                            <?php elseif ($showUser !== null && $showUser->can('assessments.verify')): ?>
                                                <form method="post" action="<?= esc(site_url('assessments/' . $assessment['id'] . '/verify')) ?>" class="mb-0">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Verify</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
