<?php
/**
 * Service records for one reservist: trainings, physical fitness tests,
 * professional eligibilities, and rank history.
 *
 * These four tables are what EligibilityService reads, so everything here is
 * an input to the promotion assessment rather than a display nicety. Each
 * panel is a table of what is on file plus a single-row add form, shown only
 * to users holding `reservists.update`.
 *
 * Expects: $reservist, $trainings, $fitnessTests, $eligibilities,
 *          $rankHistory, $trainingTypes, $ranks, $canEdit
 */
$recordsBase = site_url('reservists/' . $reservist->id . '/records');

/** Renders the delete button for one record row. */
$deleteButton = static function (string $type, int $recordId) use ($recordsBase, $canEdit): string {
    if (! $canEdit) {
        return '';
    }

    return '<form method="post" action="' . esc($recordsBase . '/' . $type . '/' . $recordId . '/delete', 'attr') . '" class="mb-0 d-inline">'
        . csrf_field()
        . '<button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove this record?" aria-label="Remove record">'
        . '<i class="bi bi-trash"></i></button></form>';
};
?>

<div class="col-12">
    <div class="card shadow-sm">
        <div class="card-header">Service Records</div>
        <div class="card-body">
            <p class="text-muted small">
                Training, fitness, eligibility, and rank-history entries are the evidence the
                eligibility assessment reads. A promotion assessment can only be as complete as
                what is recorded here.
            </p>

            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-trainings" data-bs-toggle="tab" data-bs-target="#pane-trainings" type="button" role="tab" aria-controls="pane-trainings" aria-selected="true">
                        Trainings <span class="badge text-bg-secondary"><?= count($trainings) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-fitness" data-bs-toggle="tab" data-bs-target="#pane-fitness" type="button" role="tab" aria-controls="pane-fitness" aria-selected="false">
                        Fitness Tests <span class="badge text-bg-secondary"><?= count($fitnessTests) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-eligibilities" data-bs-toggle="tab" data-bs-target="#pane-eligibilities" type="button" role="tab" aria-controls="pane-eligibilities" aria-selected="false">
                        Eligibilities <span class="badge text-bg-secondary"><?= count($eligibilities) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-rank-history" data-bs-toggle="tab" data-bs-target="#pane-rank-history" type="button" role="tab" aria-controls="pane-rank-history" aria-selected="false">
                        Rank History <span class="badge text-bg-secondary"><?= count($rankHistory) ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-3">

                <!-- Trainings -->
                <div class="tab-pane fade show active" id="pane-trainings" role="tabpanel" aria-labelledby="tab-trainings">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Completed</th>
                                    <th>Issuing Authority</th>
                                    <th>Certificate No.</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($trainings === []): ?>
                                    <tr><td colspan="6" class="text-muted">No training is on file.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($trainings as $row): ?>
                                        <?php
                                        $type = null;
                                        foreach ($trainingTypes as $candidate) {
                                            if ((int) $candidate['id'] === (int) $row['training_type_id']) {
                                                $type = $candidate;
                                                break;
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <?= esc($type['training_name'] ?? '—') ?>
                                                <?php if (($type['is_mles'] ?? 0)): ?>
                                                    <span class="badge text-bg-info">MLES</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= esc($row['training_title'] ?? '—') ?></td>
                                            <td><?= esc($row['date_completed'] ?? '—') ?></td>
                                            <td><?= esc($row['issuing_authority'] ?? '—') ?></td>
                                            <td><?= esc($row['certificate_number'] ?? '—') ?></td>
                                            <td class="text-end"><?= $deleteButton('trainings', (int) $row['id']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= esc($recordsBase . '/trainings') ?>" class="row g-2 align-items-end border-top pt-3">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label" for="training_type_id">Training type</label>
                                <select class="form-select form-select-sm" id="training_type_id" name="training_type_id" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($trainingTypes as $type): ?>
                                        <option value="<?= esc($type['id'], 'attr') ?>">
                                            <?= esc($type['training_name']) ?><?= ($type['is_mles'] ?? 0) ? ' (MLES)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="training_title">Course title</label>
                                <input type="text" class="form-control form-control-sm" id="training_title" name="training_title" maxlength="150">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="date_completed">Completed</label>
                                <input type="date" class="form-control form-control-sm" id="date_completed" name="date_completed">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="training_issuing_authority">Issued by</label>
                                <input type="text" class="form-control form-control-sm" id="training_issuing_authority" name="issuing_authority" maxlength="150">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Add training
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Physical fitness tests -->
                <div class="tab-pane fade" id="pane-fitness" role="tabpanel" aria-labelledby="tab-fitness">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Test date</th>
                                    <th>Result</th>
                                    <th>Score</th>
                                    <th>Remarks</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($fitnessTests === []): ?>
                                    <tr><td colspan="5" class="text-muted">No physical fitness test is on file.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fitnessTests as $row): ?>
                                        <tr>
                                            <td><?= esc($row['test_date']) ?></td>
                                            <td>
                                                <span class="badge text-bg-<?= $row['result'] === 'pass' ? 'success' : 'danger' ?>">
                                                    <?= esc(ucfirst((string) $row['result'])) ?>
                                                </span>
                                            </td>
                                            <td><?= esc($row['score'] ?? '—') ?></td>
                                            <td><?= esc($row['remarks'] ?? '—') ?></td>
                                            <td class="text-end"><?= $deleteButton('fitness-tests', (int) $row['id']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= esc($recordsBase . '/fitness-tests') ?>" class="row g-2 align-items-end border-top pt-3">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label" for="test_date">Test date</label>
                                <input type="date" class="form-control form-control-sm" id="test_date" name="test_date" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="result">Result</label>
                                <select class="form-select form-select-sm" id="result" name="result" required>
                                    <option value="pass">Pass</option>
                                    <option value="fail">Fail</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="score">Score</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="score" name="score">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="pft_remarks">Remarks</label>
                                <input type="text" class="form-control form-control-sm" id="pft_remarks" name="remarks" maxlength="255">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Add test
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Professional eligibilities -->
                <div class="tab-pane fade" id="pane-eligibilities" role="tabpanel" aria-labelledby="tab-eligibilities">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Eligibility</th>
                                    <th>Rating</th>
                                    <th>Obtained</th>
                                    <th>Issuing authority</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($eligibilities === []): ?>
                                    <tr><td colspan="5" class="text-muted">No professional eligibility is on file.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($eligibilities as $row): ?>
                                        <tr>
                                            <td><?= esc($row['eligibility_title']) ?></td>
                                            <td><?= esc($row['rating'] ?? '—') ?></td>
                                            <td><?= esc($row['date_obtained'] ?? '—') ?></td>
                                            <td><?= esc($row['issuing_authority'] ?? '—') ?></td>
                                            <td class="text-end"><?= $deleteButton('eligibilities', (int) $row['id']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= esc($recordsBase . '/eligibilities') ?>" class="row g-2 align-items-end border-top pt-3">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label" for="eligibility_title">Eligibility</label>
                                <input type="text" class="form-control form-control-sm" id="eligibility_title" name="eligibility_title" maxlength="150" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="rating">Rating</label>
                                <input type="text" class="form-control form-control-sm" id="rating" name="rating" maxlength="50">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="date_obtained">Obtained</label>
                                <input type="date" class="form-control form-control-sm" id="date_obtained" name="date_obtained">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="eligibility_issuing_authority">Issuing authority</label>
                                <input type="text" class="form-control form-control-sm" id="eligibility_issuing_authority" name="issuing_authority" maxlength="150">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Add eligibility
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Rank history -->
                <div class="tab-pane fade" id="pane-rank-history" role="tabpanel" aria-labelledby="tab-rank-history">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Effective date</th>
                                    <th>Order reference</th>
                                    <th>Remarks</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rankHistory === []): ?>
                                    <tr><td colspan="5" class="text-muted">No rank history is on file.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rankHistory as $row): ?>
                                        <?php
                                        $rankName = '—';
                                        foreach ($ranks as $candidate) {
                                            if ((int) $candidate['id'] === (int) $row['rank_id']) {
                                                $rankName = $candidate['rank_name'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?= esc($rankName) ?></td>
                                            <td><?= esc($row['effective_date']) ?></td>
                                            <td><?= esc($row['order_reference'] ?? '—') ?></td>
                                            <td><?= esc($row['remarks'] ?? '—') ?></td>
                                            <td class="text-end"><?= $deleteButton('rank-history', (int) $row['id']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= esc($recordsBase . '/rank-history') ?>" class="row g-2 align-items-end border-top pt-3">
                            <?= csrf_field() ?>
                            <div class="col-md-3">
                                <label class="form-label" for="rank_id">Rank</label>
                                <select class="form-select form-select-sm" id="rank_id" name="rank_id" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($ranks as $rankOption): ?>
                                        <option value="<?= esc($rankOption['id'], 'attr') ?>"><?= esc($rankOption['rank_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="effective_date">Effective date</label>
                                <input type="date" class="form-control form-control-sm" id="effective_date" name="effective_date" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="order_reference">Order reference</label>
                                <input type="text" class="form-control form-control-sm" id="order_reference" name="order_reference" maxlength="100">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="rank_remarks">Remarks</label>
                                <input type="text" class="form-control form-control-sm" id="rank_remarks" name="remarks" maxlength="255">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Add entry
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>
