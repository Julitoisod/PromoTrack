<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var App\Entities\Reservist $reservist
 * @var array|null             $rotc       RotcProgressService::summarize(), null when not a student reservist
 * @var array|null             $assessment
 * @var array|null             $package
 * @var bool                   $rcdgLevel
 */
$toneIcon = ['success' => 'bi-check-circle-fill', 'warning' => 'bi-hourglass-split', 'danger' => 'bi-exclamation-octagon-fill'];
?>

<?= view('my/_hero', [
    'reservist' => $reservist,
    'unit'      => $unit,
    'company'   => $company,
    'photoUrl'  => $photoUrl,
    'subtitle'  => $rotc !== null ? 'Student Reservist Dashboard' : 'Reservist Dashboard',
]) ?>

<?php if ($rcdgLevel): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div><strong>RCDG level.</strong> Promotion for a Master Sergeant is processed at the RCDG level and
            cannot be applied for in PromoTrack.</div>
    </div>
<?php endif; ?>

<?php if ($rotc !== null): ?>
    <?php
    [$yearPart, $semPart] = array_pad(explode(' Year ', $rotc['currentLevel'], 2), 2, '');
    $classification       = $rotc['classification'];
    ?>
    <div class="row g-3 mb-3 portal-tiles">
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-blue">
                <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                <div class="tile-value"><?= esc($yearPart) ?> Year</div>
                <div class="tile-value-sm"><?= esc(str_replace('Sem', 'Semester', $semPart)) ?></div>
                <div class="tile-label">Current Academic Level</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-green">
                <i class="bi bi-chevron-double-up" aria-hidden="true"></i>
                <div class="tile-value"><?= esc($rotc['currentLabel']) ?></div>
                <div class="tile-value-sm">
                    <?= $rotc['rankHidden'] ? 'Rank not shown' : esc($rotc['cadetRank'] ?? 'Cadet') ?>
                </div>
                <div class="tile-label">Current Rank (Cadet)</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-purple">
                <i class="bi bi-star-fill" aria-hidden="true"></i>
                <div class="tile-value"><?= esc($classification ?? 'Not set') ?></div>
                <div class="tile-label">Reserve Classification</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-<?= esc($rotc['trainingStatus']['tone']) ?>">
                <i class="bi bi-clipboard-check-fill" aria-hidden="true"></i>
                <div class="tile-value"><?= esc($rotc['trainingStatus']['label']) ?></div>
                <div class="tile-label">Training Status</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card portal-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>My ROTC Progress</span>
                    <a href="<?= esc(site_url('my/rotc')) ?>" class="portal-link">View Details <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <ol class="ms-steps">
                        <?php foreach ($rotc['msCourses'] as $code => $course): ?>
                            <li class="<?= $course['done'] ? 'done' : '' ?>">
                                <span class="ms-dot"><i class="bi bi-check-lg"></i></span>
                                <span class="ms-name"><strong><?= esc($code) ?></strong> (<?= esc($course['label']) ?>)</span>
                                <span class="badge <?= $course['done'] ? 'badge-done' : 'badge-pending' ?>">
                                    <?= $course['done'] ? 'Completed' : 'Pending' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <?= view('my/_information', ['reservist' => $reservist, 'unit' => $unit, 'company' => $company, 'rank' => $rank]) ?>
        </div>
    </div>

    <?php $status = $rotc['status']; ?>
    <div class="status-card status-<?= esc($status['tone']) ?>">
        <span class="status-icon"><i class="bi <?= esc($toneIcon[$status['tone']]) ?>"></i></span>
        <div class="status-text">
            <div class="status-kicker">Your Current Status</div>
            <div class="status-title"><?= esc($status['title']) ?></div>
            <p class="mb-0"><?= esc($status['message']) ?></p>
            <?php if (! $rotc['rankHidden'] && $rotc['reserveRank'] !== null): ?>
                <p class="status-rank mb-0">Reserve rank earned through ROTC: <strong><?= esc($rotc['reserveRank']) ?></strong>
                    <span class="text-muted">(for your unit to verify)</span></p>
            <?php endif; ?>
        </div>
        <i class="bi bi-award status-emblem" aria-hidden="true"></i>
    </div>

<?php else: ?>
    <?php $standing = $assessment['promotion_status'] ?? null; ?>
    <div class="row g-3 mb-3 portal-tiles">
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-green">
                <i class="bi bi-chevron-double-up" aria-hidden="true"></i>
                <div class="tile-value"><?= esc($rank['rank_name'] ?? '—') ?></div>
                <div class="tile-label">Current Rank</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-blue">
                <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                <div class="tile-value"><?= view('components/promotion_status', ['status' => $standing]) ?></div>
                <div class="tile-label">Promotion Standing</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-purple">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <div class="tile-value"><?= $reservist->getTimeInGradeMonths() !== null ? esc((string) $reservist->getTimeInGradeMonths()) . ' mo.' : '—' ?></div>
                <div class="tile-label">Time in Grade</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="portal-tile tile-warning">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                <div class="tile-value"><?= $package !== null ? esc(ucwords(str_replace('_', ' ', $package['status']))) : 'None yet' ?></div>
                <div class="tile-label">Promotion Package</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card portal-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Promotion Standing</span>
                    <a href="<?= esc(site_url('my/promotion')) ?>" class="portal-link">My Documents <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if ($assessment === null): ?>
                        <p class="text-muted mb-0">Your unit has not run an eligibility assessment on your record yet.</p>
                    <?php else: ?>
                        <?php $timeInGrade = App\Models\PromotionAssessmentModel::timeInGradeCriterion($assessment); ?>
                        <p class="mb-2"><?= view('components/promotion_status', ['status' => $standing]) ?>
                            <span class="text-muted ms-2">as of <?= esc((string) $assessment['as_of_date']) ?></span></p>
                        <?php if ($timeInGrade !== null): ?>
                            <p class="mb-0"><?= esc($timeInGrade['detail']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($reservist->is_student && ($reservist->educational_attainment ?? null) === null): ?>
                        <p class="text-muted small mt-3 mb-0">Student reservist? Set your educational attainment to
                            Undergraduate on <a href="<?= esc(site_url('my/profile')) ?>">My Profile</a> to see your ROTC progress here.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <?= view('my/_information', ['reservist' => $reservist, 'unit' => $unit, 'company' => $company, 'rank' => $rank]) ?>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
