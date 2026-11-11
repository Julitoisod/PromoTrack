<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * Companies offered under each CDC on the sign-up form.
 *
 * @var array<int, array> $cdcs      active CDC units keyed by id
 * @var int|null          $selected  CDC filter, or null for all
 * @var list<array>       $companies
 */
$base       = site_url('admin/companies');
$returnCdc  = $selected !== null ? (string) $selected : '';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Companies</h1>
        <p class="page-subtitle">The companies listed under each CDC in the sign-up form's "Select Company".</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Add Company</div>
    <div class="card-body">
        <?php if ($cdcs === []): ?>
            <p class="text-muted mb-0">There is no active CDC yet. Add one under Reference Data first.</p>
        <?php else: ?>
            <form method="post" action="<?= esc($base) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label class="form-label" for="new_cdc">CDC</label>
                    <select class="form-select" id="new_cdc" name="cdc_id" required>
                        <option value="">Select CDC</option>
                        <?php foreach ($cdcs as $id => $cdc): ?>
                            <option value="<?= esc((string) $id) ?>" <?= (string) old('cdc_id', $returnCdc) === (string) $id ? 'selected' : '' ?>>
                                <?= esc($cdc['unit_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="new_name">Company name</label>
                    <input type="text" class="form-control" id="new_name" name="company_name" maxlength="150" required
                           placeholder="e.g. Foxtrot Company, Ready Reserve Infantry Battalion" value="<?= esc(old('company_name') ?? '') ?>">
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Company</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-pills mb-3 flex-wrap">
    <li class="nav-item">
        <a class="nav-link <?= $selected === null ? 'active' : '' ?>" href="<?= esc($base) ?>">All CDCs</a>
    </li>
    <?php foreach ($cdcs as $id => $cdc): ?>
        <li class="nav-item">
            <a class="nav-link <?= $selected === $id ? 'active' : '' ?>" href="<?= esc($base . '?cdc=' . $id) ?>"><?= esc($cdc['unit_name']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="card-body">
        <?php if ($companies === []): ?>
            <div class="empty-state">
                <i class="bi bi-buildings"></i>
                <p class="mb-0">No companies yet<?= $selected !== null ? ' for ' . esc($cdcs[$selected]['unit_name']) : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Company name</th>
                            <th scope="col">CDC</th>
                            <th class="text-center" scope="col">Reservists</th>
                            <th class="text-center" scope="col">Status</th>
                            <th class="text-end" scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <?php
                            $active   = (int) $company['is_active'] === 1;
                            $formId   = 'company-' . $company['id'];
                            $assigned = (int) $company['reservist_count'];
                            ?>
                            <tr class="<?= $active ? '' : 'text-muted' ?>">
                                <td class="w-50">
                                    <form method="post" action="<?= esc($base . '/' . $company['id']) ?>" id="<?= esc($formId) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_cdc" value="<?= esc($returnCdc) ?>">
                                    </form>
                                    <input type="text" class="form-control form-control-sm" form="<?= esc($formId) ?>" name="company_name"
                                           maxlength="150" required aria-label="Company name" value="<?= esc($company['unit_name']) ?>">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" form="<?= esc($formId) ?>" name="cdc_id" aria-label="CDC"
                                            <?= $assigned > 0 ? 'disabled title="Has reservists, so it stays under this CDC"' : '' ?>>
                                        <?php foreach ($cdcs as $id => $cdc): ?>
                                            <option value="<?= esc((string) $id) ?>" <?= (int) $company['parent_unit_id'] === $id ? 'selected' : '' ?>>
                                                <?= esc($cdc['unit_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-center"><?= esc((string) $assigned) ?></td>
                                <td class="text-center">
                                    <span class="badge text-bg-<?= $active ? 'success' : 'secondary' ?>"><?= $active ? 'Active' : 'Hidden' ?></span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="submit" form="<?= esc($formId) ?>" class="btn btn-sm btn-outline-primary">Save</button>
                                    <form method="post" action="<?= esc($base . '/' . $company['id'] . '/toggle') ?>" class="d-inline mb-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_cdc" value="<?= esc($returnCdc) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                data-confirm="<?= $active ? 'Hide this company from the sign-up form?' : 'Show this company on the sign-up form again?' ?>">
                                            <?= $active ? 'Hide' : 'Show' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-3 mb-0">
                Hidden companies no longer appear at sign-up; reservists already in them keep them.
                A company with reservists stays under its CDC.
            </p>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
