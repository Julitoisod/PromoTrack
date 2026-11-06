<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-title">My Promotion</h1>
</div>

<?php if ($reservist === null): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-person-exclamation"></i>
                <p class="mb-1">Your account is not linked to a reservist record yet.</p>
                <p class="text-muted mb-0">
                    Ask your unit S1 clerk to check that the AFP serial number on your
                    record matches the one you signed up with.
                </p>
            </div>
        </div>
    </div>
<?php else: ?>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">My Record</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt>
                        <dd class="col-sm-7"><?= esc($reservist->getFullName()) ?></dd>

                        <dt class="col-sm-5">Reservist #</dt>
                        <dd class="col-sm-7"><?= esc($reservist->reservist_number) ?></dd>

                        <dt class="col-sm-5">Rank</dt>
                        <dd class="col-sm-7"><?= esc($rank['rank_name'] ?? '—') ?></dd>

                        <dt class="col-sm-5">Unit</dt>
                        <dd class="col-sm-7"><?= esc($unit['unit_name'] ?? '—') ?></dd>

                        <dt class="col-sm-5">Company</dt>
                        <dd class="col-sm-7"><?= esc($company['unit_name'] ?? '—') ?></dd>

                        <dt class="col-sm-5">Education Track</dt>
                        <dd class="col-sm-7"><?= esc($track['track_name'] ?? '—') ?></dd>

                        <dt class="col-sm-5">Schooling</dt>
                        <dd class="col-sm-7"><?= esc($reservist->getSchoolingLabel()) ?></dd>

                        <dt class="col-sm-5">Last Promoted</dt>
                        <dd class="col-sm-7">
                            <?= $reservist->date_of_last_promotion !== null
                                ? esc($reservist->date_of_last_promotion->toDateString())
                                : '—' ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Promotion Standing</div>
                <div class="card-body">
                    <p class="mb-2">
                        <?= view('components/promotion_status', ['status' => $assessment['promotion_status'] ?? null]) ?>
                    </p>
                    <?php if ($assessment === null): ?>
                        <p class="text-muted mb-0">
                            Your unit has not run an eligibility assessment on your record yet.
                            Keep your documents uploaded so your package is ready when they do.
                        </p>
                    <?php else: ?>
                        <?php $timeInGrade = App\Models\PromotionAssessmentModel::timeInGradeCriterion($assessment); ?>
                        <dl class="row mb-0">
                            <dt class="col-sm-6">Time in grade</dt>
                            <dd class="col-sm-6">
                                <?= $assessment['time_in_grade_months'] !== null
                                    ? esc((string) $assessment['time_in_grade_months']) . ' month(s)'
                                    : '—' ?>
                            </dd>
                            <?php if (($timeInGrade['remaining_months'] ?? null) !== null): ?>
                                <dt class="col-sm-6">Months to go</dt>
                                <dd class="col-sm-6"><?= esc((string) $timeInGrade['remaining_months']) ?> month(s)</dd>
                            <?php elseif (($timeInGrade['overdue_months'] ?? null) !== null): ?>
                                <dt class="col-sm-6">Overdue by</dt>
                                <dd class="col-sm-6"><?= esc((string) $timeInGrade['overdue_months']) ?> month(s)</dd>
                            <?php endif; ?>
                            <dt class="col-sm-6">Assessed as of</dt>
                            <dd class="col-sm-6"><?= esc((string) $assessment['as_of_date']) ?></dd>
                        </dl>
                        <?php if ($timeInGrade !== null): ?>
                            <p class="text-muted small mb-0 mt-2"><?= esc($timeInGrade['detail']) ?></p>
                        <?php endif; ?>
                        <?php if ((int) $assessment['requires_manual_review'] === 1): ?>
                            <p class="text-muted small mb-0 mt-2">
                                Some criteria could not be checked automatically and are with your unit for review.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($package === null): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="bi bi-folder2-open"></i>
                    <p class="mb-1">You have no open promotion package yet.</p>
                    <p class="text-muted">
                        Start one to get your thirteen-item document checklist (Items A&ndash;M).
                        You upload the documents; your unit S1 clerk verifies them and submits
                        the package to the 1502 CDC.
                    </p>
                    <form method="post" action="<?= esc(site_url('my/promotion/package')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-folder-plus me-1"></i> Start my promotion package
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php
        $packageStatus = $package['status'];
        $statusBadge   = match ($packageStatus) {
            'closed', 'promotion_order_recorded' => 'success',
            'returned_to_unit', 'returned_by_rcdg', 'disapproved' => 'danger',
            'requirements_incomplete'                            => 'warning',
            default                                              => 'secondary',
        };
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Package <?= esc($package['package_number']) ?></span>
                <span>
                    <span class="badge text-bg-<?= esc($statusBadge) ?>"><?= esc(ucwords(str_replace('_', ' ', $packageStatus))) ?></span>
                    <span class="text-muted ms-2"><?= esc((string) $package['completeness_percentage']) ?>% complete</span>
                </span>
            </div>
            <div class="card-body">
                <?php if ($missing !== []): ?>
                    <div class="alert alert-warning" role="alert">
                        <strong>Requirements incomplete.</strong>
                        Your package cannot move to the next step until every item below is
                        uploaded. Still missing
                        <?= esc((string) count($missing)) ?> of <?= esc((string) count($checklist)) ?>:
                        <?= esc(implode(', ', array_column($missing, 'document_code'))) ?>.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <strong>All documents submitted.</strong>
                        Your unit S1 clerk verifies them before the package is forwarded.
                    </div>
                <?php endif; ?>

                <?= view('packages/_checklist', ['checklist' => $checklist, 'status' => $packageStatus]) ?>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>
<?= $this->endSection() ?>
