<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Promotion Rules</h1>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Every threshold below is a placeholder until confirmed by an official AFP / Philippine Army
    issuance. Changing a threshold creates a <strong>new version</strong> — the superseded one stays
    on record so past assessments remain explainable.
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">Add rank-transition rule</div>
    <div class="card-body">
        <form method="post" action="<?= esc(site_url('admin/rules')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label" for="from_rank_id">From rank</label>
                <select class="form-select form-select-sm" id="from_rank_id" name="from_rank_id" required>
                    <option value="">Select…</option>
                    <?php foreach ($ranks as $rank): ?>
                        <option value="<?= esc($rank['id'], 'attr') ?>"><?= esc($rank['rank_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= view('components/form_errors', ['field' => 'from_rank_id']) ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="to_rank_id">To rank</label>
                <select class="form-select form-select-sm" id="to_rank_id" name="to_rank_id" required>
                    <option value="">Select…</option>
                    <?php foreach ($ranks as $rank): ?>
                        <option value="<?= esc($rank['id'], 'attr') ?>"><?= esc($rank['rank_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= view('components/form_errors', ['field' => 'to_rank_id']) ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="rule_name">Rule name</label>
                <input type="text" class="form-control form-control-sm" id="rule_name" name="rule_name"
                       maxlength="150" value="<?= esc(old('rule_name') ?? '') ?>" required>
                <?= view('components/form_errors', ['field' => 'rule_name']) ?>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add rule
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">Configured rules</div>
    <div class="card-body">
        <?php if ($rules === []): ?>
            <div class="empty-state">
                <i class="bi bi-sliders"></i>
                No promotion rules are configured yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="rulesTable">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Transition</th>
                            <th>In force</th>
                            <th>Min. time in grade</th>
                            <th>MLES</th>
                            <th>PFT</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $rule): ?>
                            <?php $effective = $rule['effective']; ?>
                            <tr>
                                <td>
                                    <a href="<?= esc(site_url('admin/rules/' . $rule['id'])) ?>">
                                        <?= esc($rule['rule_name']) ?>
                                    </a>
                                </td>
                                <td><?= esc($rule['from_rank_name']) ?> → <?= esc($rule['to_rank_name']) ?></td>
                                <td>
                                    <?php if ($effective !== null): ?>
                                        v<?= esc((string) $effective['version_number']) ?>
                                        <span class="text-muted small">from <?= esc($effective['effective_date']) ?></span>
                                    <?php elseif ((int) $rule['version_count'] > 0): ?>
                                        <span class="badge text-bg-info">By education track</span>
                                        <span class="text-muted small"><?= esc((string) $rule['version_count']) ?> version(s)</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">No version</span>
                                    <?php endif; ?>
                                </td>
                                <?php $minTig = $effective['minimum_time_in_grade_months'] ?? null; ?>
                                <td><?= $minTig !== null ? esc((string) $minTig) . ' mo(s)' : '—' ?></td>
                                <td><?= ((int) ($effective['requires_mles'] ?? 0)) === 1 ? 'Required' : '—' ?></td>
                                <td><?= ((int) ($effective['requires_physical_fitness_test'] ?? 0)) === 1 ? 'Required' : '—' ?></td>
                                <td>
                                    <span class="badge text-bg-<?= ((int) $rule['is_active']) === 1 ? 'success' : 'secondary' ?>">
                                        <?= ((int) $rule['is_active']) === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= esc(site_url('admin/rules/' . $rule['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                        Versions
                                    </a>
                                    <form method="post" action="<?= esc(site_url('admin/rules/' . $rule['id'] . '/toggle')) ?>" class="d-inline mb-0">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                data-confirm="<?= ((int) $rule['is_active']) === 1 ? 'Deactivate' : 'Reactivate' ?> this rule?">
                                            <?= ((int) $rule['is_active']) === 1 ? 'Deactivate' : 'Reactivate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && document.getElementById('rulesTable')) {
            jQuery('#rulesTable').DataTable({ order: [] });
        }
    });
</script>
<?= $this->endSection() ?>
