<?php
/**
 * Search / CDC / company filter bar shared by the admin panel's lists.
 *
 * @var array{q: string, cdc: int, company: int} $filters
 * @var list<array>                              $filterCdcs
 * @var list<array>                              $filterCompanies
 * @var string                                   $action  URL the form submits to
 */
?>
<form method="get" action="<?= esc($action) ?>" class="row g-2 align-items-end mb-3 admin-filters">
    <div class="col-md-4">
        <label class="form-label small fw-semibold" for="f_q">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="f_q" name="q" value="<?= esc($filters['q']) ?>" placeholder="Name or serial number">
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold" for="f_cdc">CDC</label>
        <select class="form-select" id="f_cdc" name="cdc">
            <option value="">All CDCs</option>
            <?php foreach ($filterCdcs as $cdc): ?>
                <option value="<?= esc((string) $cdc['id']) ?>" <?= $filters['cdc'] === (int) $cdc['id'] ? 'selected' : '' ?>><?= esc($cdc['unit_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold" for="f_company">Company</label>
        <select class="form-select" id="f_company" name="company">
            <option value="">All companies</option>
            <?php foreach ($filterCompanies as $company): ?>
                <option value="<?= esc((string) $company['id']) ?>" <?= $filters['company'] === (int) $company['id'] ? 'selected' : '' ?>>
                    <?= esc(preg_replace('/,.*$/', '', (string) $company['unit_name'])) ?><?= $filters['cdc'] === 0 && $company['parent_unit_name'] !== null ? ' (' . esc($company['parent_unit_name']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i> Filter</button>
        <?php if ($filters['q'] !== '' || $filters['cdc'] !== 0 || $filters['company'] !== 0): ?>
            <a href="<?= esc($action) ?>" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
    </div>
</form>
