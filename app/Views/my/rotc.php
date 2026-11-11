<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var array|null                         $rotc
 * @var Config\RotcProgression             $config
 * @var App\Services\RotcProgressService   $service
 */
$stateBadge = [
    'done'    => ['badge-done', 'Done'],
    'pending' => ['badge-pending-warn', 'To fill in'],
    'blocked' => ['badge-blocked', 'Action needed'],
    'locked'  => ['badge-locked', 'Locked'],
];
$choiceGroup = static function (string $name, array $options, ?string $current, string $stage): string {
    $html = '<div class="choice-group" role="radiogroup">';

    foreach ($options as $value => $label) {
        $id    = $stage . '_' . $name . '_' . $value;
        $html .= '<input type="radio" class="btn-check" name="' . esc($name) . '" id="' . esc($id) . '" value="' . esc($value) . '"'
            . ($current === $value ? ' checked' : '') . '>'
            . '<label class="btn btn-choice" for="' . esc($id) . '">' . esc($label) . '</label>';
    }

    return $html . '</div>';
};
?>

<div class="page-head">
    <div>
        <h1 class="page-title">ROTC Progress</h1>
        <p class="page-subtitle">Record where you stand at each semester. Your unit S1 clerk verifies these answers.</p>
    </div>
</div>

<?php if ($rotc === null): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-mortarboard"></i>
                <p class="mb-1">ROTC progress is for undergraduate student reservists.</p>
                <p class="text-muted mb-3">
                    If you are still studying, set your educational attainment to <strong>Undergraduate</strong>,
                    your schooling status to <strong>Currently enrolled</strong>, and your year level on My Profile.
                </p>
                <a href="<?= esc(site_url('my/profile')) ?>" class="btn btn-primary"><i class="bi bi-person-fill me-1"></i> Go to My Profile</a>
            </div>
        </div>
    </div>
<?php else: ?>

    <div class="rotc-summary card portal-card mb-3">
        <div class="card-body">
            <div class="rotc-summary-grid">
                <div><span>Current Level</span><strong><?= esc($rotc['currentLabel']) ?> · <?= esc($rotc['currentLevel']) ?></strong></div>
                <div><span>Cadet Rank</span><strong><?= $rotc['rankHidden'] ? 'Not shown' : esc($rotc['cadetRank'] ?? '—') ?></strong></div>
                <div><span>Reserve Rank (earned)</span><strong><?= $rotc['rankHidden'] ? 'Not shown' : esc($rotc['reserveRank'] ?? '—') ?></strong></div>
                <div><span>Classification</span><strong><?= esc($rotc['classification'] ?? 'Not set') ?></strong></div>
                <div><span>Status</span><strong class="text-<?= esc($rotc['status']['tone']) ?>"><?= esc($rotc['status']['title']) ?></strong></div>
            </div>
        </div>
    </div>

    <?php
    // Certificate of Registration for the semester the student is in now.
    $corState = $cor === null || $cor['cor_path'] === null
        ? ['Not uploaded', 'secondary']
        : (App\Models\StudentEnrollmentModel::STATUSES[$cor['status']] ?? ['Pending', 'warning']);
    ?>
    <div class="card portal-card mb-3 cor-card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span><i class="bi bi-file-earmark-text me-1"></i> Certificate of Registration (COR) &middot; <?= esc($reservist->student_year_level) ?></span>
            <span class="badge text-bg-<?= esc($corState[1]) ?>"><?= esc($corState[0]) ?></span>
        </div>
        <div class="card-body">
            <?php if (($cor['status'] ?? null) === 'verified'): ?>
                <p class="mb-2 text-success"><i class="bi bi-check-circle-fill me-1"></i> Your enrollment for this semester is verified.</p>
            <?php elseif (($cor['status'] ?? null) === 'rejected'): ?>
                <p class="mb-2 text-danger"><i class="bi bi-exclamation-circle-fill me-1"></i> Your COR was not accepted. Upload a clear copy.</p>
            <?php else: ?>
                <p class="mb-2 text-muted">Upload your COR for this semester so your enrollment can be verified.</p>
            <?php endif; ?>

            <form method="post" action="<?= esc(site_url('my/cor')) ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="file" class="form-control form-control-sm w-auto" name="cor" accept=".pdf,.jpg,.jpeg,.png" required aria-label="COR file">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i> <?= $cor !== null && $cor['cor_path'] !== null ? 'Replace COR' : 'Upload COR' ?></button>
                <?php if ($cor !== null && $cor['cor_path'] !== null): ?>
                    <a href="<?= esc(site_url('my/cor')) ?>" target="_blank" rel="noopener" class="small ms-1"><i class="bi bi-paperclip"></i> <?= esc($cor['cor_filename'] ?? 'View COR') ?></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="rotc-ladder">
        <?php foreach ($rotc['stages'] as $code => $stage): ?>
            <?php
            $row    = $stage['row'];
            $cfg    = $stage['config'];
            $locked = $stage['state'] === 'locked';
            $checks = $service->checksFor($code);
            [$badgeClass, $badgeText] = $stateBadge[$stage['state']];
            ?>
            <section class="rotc-stage state-<?= esc($stage['state']) ?><?= $code === $rotc['currentStage'] ? ' is-current' : '' ?>" id="stage-<?= esc($code) ?>">
                <div class="rotc-stage-marker"><?= esc($stage['label']) ?></div>

                <div class="card portal-card rotc-stage-card">
                    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <span>
                            <strong><?= esc($stage['label']) ?></strong>
                            <span class="text-muted">· <?= esc($cfg['level']) ?></span>
                            <?php if ($code === $rotc['currentStage']): ?>
                                <span class="badge text-bg-dark ms-1">You are here</span>
                            <?php endif; ?>
                        </span>
                        <span class="badge <?= esc($badgeClass) ?>"><?= esc($badgeText) ?></span>
                    </div>
                    <div class="card-body">
                        <h3 class="rotc-stage-title"><?= esc($cfg['title']) ?></h3>
                        <?php if (! empty($cfg['note'])): ?>
                            <p class="rotc-stage-note"><?= esc($cfg['note']) ?></p>
                        <?php endif; ?>

                        <form method="post" action="<?= esc(site_url('my/rotc/' . $code)) ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <fieldset <?= $locked ? 'disabled' : '' ?>>
                                <?php foreach ($cfg['fields'] as $field): ?>
                                    <?php if ($field === 'checks' && $checks !== []): ?>
                                        <div class="rotc-field">
                                            <span class="rotc-field-label"><?= $code === 'E7' ? 'Completed courses and good standing' : 'Tick what you have' ?></span>
                                            <?php if ($code === 'E7'): ?>
                                                <div class="rotc-subhead">MS courses</div>
                                            <?php endif; ?>
                                            <div class="check-grid">
                                                <?php foreach ($checks as $key => $label): ?>
                                                    <?php if ($code === 'E7' && $key === 'gs_2_1'): ?>
                                                        </div><div class="rotc-subhead">Good standing</div><div class="check-grid">
                                                    <?php endif; ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="checks[]" value="<?= esc($key) ?>"
                                                               id="<?= esc($code . '_' . $key) ?>" <?= in_array($key, $row['checks'] ?? [], true) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="<?= esc($code . '_' . $key) ?>"><?= esc($label) ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if ($code === 'E7' && ! $locked): ?>
                                                <p class="small mt-2 mb-0 <?= $rotc['gmaEligible'] ? 'text-success' : 'text-muted' ?>">
                                                    <?= $rotc['gmaEligible'] ? 'All ticked: you can become GMA at E-8.' : 'Leave any box unticked and you cannot become GMA.' ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($field === 'pose'): ?>
                                        <?php $posed = in_array('pose', $row['checks'] ?? [], true); ?>
                                        <div class="rotc-field">
                                            <span class="rotc-field-label">POSE</span>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" name="checks[]" value="pose"
                                                           id="<?= esc($code) ?>_pose" <?= $posed ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= esc($code) ?>_pose">I passed POSE</label>
                                                </div>
                                                <select class="form-select form-select-sm w-auto" name="pose_rank" aria-label="Cadet rank from POSE">
                                                    <option value="">Cadet rank received…</option>
                                                    <?php foreach ($config->poseRanks as $poseRank): ?>
                                                        <option value="<?= esc($poseRank) ?>" <?= ($row['pose_rank'] ?? null) === $poseRank ? 'selected' : '' ?>><?= esc($poseRank) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php elseif ($field === 'training_result'): ?>
                                        <div class="rotc-field">
                                            <span class="rotc-field-label">Training</span>
                                            <?= $choiceGroup('training_result', ['complete' => 'Complete', 'incomplete' => 'Incomplete'], $row['training_result'] ?? null, $code) ?>
                                        </div>
                                    <?php elseif ($field === 'recommendation'): ?>
                                        <div class="rotc-field">
                                            <span class="rotc-field-label">Recommendation</span>
                                            <?= $choiceGroup('recommendation', ['recommended' => 'Recommended (CDT CPT 2CL)', 'not_recommended' => 'Not recommended (dropped)'], $row['recommendation'] ?? null, $code) ?>
                                            <div class="mt-2">
                                                <label class="form-label small mb-1" for="<?= esc($code) ?>_justification">
                                                    Justification letter <span class="text-muted">(only if not recommended; PDF, JPG or PNG)</span>
                                                </label>
                                                <input type="file" class="form-control form-control-sm" name="justification"
                                                       id="<?= esc($code) ?>_justification" accept=".pdf,.jpg,.jpeg,.png">
                                                <?php if (($row['justification_path'] ?? null) !== null): ?>
                                                    <p class="small mb-0 mt-1">
                                                        <i class="bi bi-paperclip"></i> On file:
                                                        <a href="<?= esc(site_url('my/rotc/justification')) ?>"><?= esc($row['justification_filename'] ?? 'justification letter') ?></a>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($field === 'classification'): ?>
                                        <div class="rotc-field">
                                            <span class="rotc-field-label">Classification<?= $code === 'E4' ? ' (if recommended)' : '' ?></span>
                                            <?= $choiceGroup('classification', $config->classifications, $row['classification'] ?? null, $code) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if ($locked): ?>
                                    <p class="small text-muted mb-0"><i class="bi bi-lock-fill"></i> Opens when you reach <?= esc($cfg['level']) ?>.</p>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i> Save <?= esc($stage['label']) ?></button>
                                    <?php if ($row !== null && $row['updated_at'] !== null): ?>
                                        <span class="small text-muted ms-2">Last saved <?= esc(date('M j, Y g:i A', strtotime((string) $row['updated_at']))) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </fieldset>
                        </form>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
