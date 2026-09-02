<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rankNames = [];

foreach ($ranks as $rank) {
    $rankNames[(int) $rank['id']] = $rank['rank_name'];
}

$trackNames = [];

foreach ($educationTracks as $track) {
    $trackNames[(int) $track['id']] = $track['track_name'];
}
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= esc($rule['rule_name']) ?></h1>
        <p class="page-subtitle">
            <?= esc($rankNames[(int) $rule['from_rank_id']] ?? '—') ?>
            →
            <?= esc($rankNames[(int) $rule['to_rank_id']] ?? '—') ?>
        </p>
    </div>
    <a href="<?= esc(site_url('admin/rules')) ?>" class="btn btn-outline-secondary">Back to rules</a>
</div>

<div class="card mb-4">
    <div class="card-header">New Version</div>
    <div class="card-body">
        <p class="text-muted small">
            Saving here expires the version currently in force the day before this one takes effect.
            Nothing is overwritten: assessments already computed keep citing the version that was
            actually in force when they ran.
        </p>
        <form method="post" action="<?= esc(site_url('admin/rules/' . $rule['id'] . '/versions')) ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-md-3">
                <label class="form-label" for="minimum_time_in_grade_months">Minimum time in grade (months)</label>
                <input type="number" min="0" class="form-control form-control-sm"
                       id="minimum_time_in_grade_months" name="minimum_time_in_grade_months"
                       value="<?= esc(old('minimum_time_in_grade_months') ?? '') ?>">
                <?= view('components/form_errors', ['field' => 'minimum_time_in_grade_months']) ?>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="education_track_id">Education track</label>
                <select class="form-select form-select-sm" id="education_track_id" name="education_track_id">
                    <option value="">Applies to all tracks</option>
                    <?php foreach ($educationTracks as $track): ?>
                        <option value="<?= esc($track['id'], 'attr') ?>"><?= esc($track['track_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">A track-specific version wins over the all-tracks version.</div>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="effective_date">Effective from</label>
                <input type="date" class="form-control form-control-sm" id="effective_date" name="effective_date"
                       value="<?= esc(old('effective_date') ?? date('Y-m-d')) ?>" required>
                <?= view('components/form_errors', ['field' => 'effective_date']) ?>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="expiry_date">Expires on (optional)</label>
                <input type="date" class="form-control form-control-sm" id="expiry_date" name="expiry_date"
                       value="<?= esc(old('expiry_date') ?? '') ?>">
            </div>

            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="requires_mles" name="requires_mles">
                    <label class="form-check-label" for="requires_mles">MLES training required</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1"
                           id="requires_physical_fitness_test" name="requires_physical_fitness_test">
                    <label class="form-check-label" for="requires_physical_fitness_test">Passing PFT required</label>
                </div>
            </div>

            <div class="col-md-5">
                <label class="form-label" for="additional_prerequisites">Additional prerequisites</label>
                <input type="text" class="form-control form-control-sm"
                       id="additional_prerequisites" name="additional_prerequisites"
                       value="<?= esc(old('additional_prerequisites') ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="notes">Notes / issuance reference</label>
                <input type="text" class="form-control form-control-sm" id="notes" name="notes"
                       value="<?= esc(old('notes') ?? '') ?>">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary"
                        data-confirm="Save this as a new version and expire the current one?">
                    <i class="bi bi-check2-circle me-1"></i> Save new version
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Version History</div>
    <div class="card-body">
        <?php if ($versions === []): ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                This rule has no versions yet, so it governs nothing.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Version</th>
                            <th scope="col">Track</th>
                            <th scope="col">Min. TIG</th>
                            <th scope="col">MLES</th>
                            <th scope="col">PFT</th>
                            <th scope="col">Effective</th>
                            <th scope="col">Expires</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($versions as $version): ?>
                            <?php $isEffective = $effective !== null && (int) $effective['id'] === (int) $version['id']; ?>
                            <tr class="<?= $isEffective ? 'table-success' : '' ?>">
                                <td>
                                    v<?= esc((string) $version['version_number']) ?>
                                    <?php if ($isEffective): ?>
                                        <span class="badge text-bg-success">In force</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($version['education_track_id'] !== null
                                        ? ($trackNames[(int) $version['education_track_id']] ?? 'Track #' . $version['education_track_id'])
                                        : 'All tracks') ?></td>
                                <td><?= $version['minimum_time_in_grade_months'] !== null
                                        ? esc((string) $version['minimum_time_in_grade_months']) . ' mo(s)'
                                        : '—' ?></td>
                                <td><?= ((int) $version['requires_mles']) === 1 ? 'Yes' : 'No' ?></td>
                                <td><?= ((int) $version['requires_physical_fitness_test']) === 1 ? 'Yes' : 'No' ?></td>
                                <td><?= esc($version['effective_date']) ?></td>
                                <td><?= esc($version['expiry_date'] ?? '—') ?></td>
                                <td><?= esc($version['notes'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
