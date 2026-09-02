<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$showUser = auth()->user();
$status   = $package['status'];

$statusBadge = match ($status) {
    'closed', 'promotion_order_recorded' => 'success',
    'returned_to_unit', 'returned_by_rcdg', 'disapproved' => 'danger',
    'requirements_incomplete'                            => 'warning',
    default                                              => 'secondary',
};
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= esc($package['package_number']) ?></h1>
        <span class="badge text-bg-<?= esc($statusBadge) ?> mt-1"><?= esc(ucwords(str_replace('_', ' ', $status))) ?></span>
        <span class="text-muted ms-2"><?= esc((string) $package['completeness_percentage']) ?>% complete</span>
    </div>
    <div class="d-flex gap-2">
        <?php if ($showUser !== null && $showUser->can('reports.generate')): ?>
            <?php if (in_array($status, ['rcdg_endorsed', 'forwarded_to_external_authority', 'awaiting_external_decision', 'deferred', 'approved', 'disapproved', 'promotion_order_recorded', 'closed'], true)): ?>
                <a href="<?= esc(site_url('reports/packages/' . $package['id'] . '/endorsements')) ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Endorsement Report
                </a>
            <?php endif; ?>
            <?php if ($promotionOrder !== null): ?>
                <a href="<?= esc(site_url('reports/promotion-orders/' . $promotionOrder['id'])) ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Promotion Order
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= esc(site_url('packages')) ?>" class="btn btn-outline-secondary">Back to list</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Package Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Routing Level</dt>
                    <dd class="col-sm-7"><?= esc(str_replace('_', ' ', $package['current_routing_level'])) ?></dd>
                    <dt class="col-sm-5">Created</dt>
                    <dd class="col-sm-7"><?= esc($package['created_at']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Document Checklist</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Document</th>
                        <th scope="col">Status</th>
                        <th class="text-end" scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checklist as $item): ?>
                        <?php
                        $itemBadge = match ($item['status']) {
                            'verified', 'waived' => 'success',
                            'rejected'            => 'danger',
                            'uploaded'            => 'info',
                            default               => 'secondary',
                        };
                        ?>
                        <tr>
                            <td><?= esc($item['document_code']) ?></td>
                            <td>
                                <?= esc($item['document_label']) ?>
                                <?php if ((int) $item['is_conditional'] === 1): ?>
                                    <span class="badge text-bg-light text-muted border">conditional</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?= esc($itemBadge) ?>"><?= esc(ucfirst($item['status'])) ?></span></td>
                            <td class="text-end">
                                <?php if ($item['current_version_id'] !== null): ?>
                                    <a href="<?= esc(site_url('package-documents/versions/' . $item['current_version_id'] . '/download')) ?>" class="btn btn-sm btn-outline-secondary" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($showUser !== null && $showUser->can('documents.upload') && in_array($status, ['draft', 'requirements_incomplete', 'returned_to_unit'], true)): ?>
                                    <form method="post" action="<?= esc(site_url('package-documents/' . $item['id'] . '/upload')) ?>" enctype="multipart/form-data" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="file" name="document" class="form-control form-control-sm d-inline-block w-auto" required>
                                        <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($showUser !== null && $item['status'] === 'uploaded'): ?>
                                    <?php if ($showUser->can('documents.verify')): ?>
                                        <form method="post" action="<?= esc(site_url('package-documents/' . $item['id'] . '/verify')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success">Verify</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($showUser->can('documents.reject')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-<?= esc((string) $item['id']) ?>">Reject</button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($showUser !== null && $showUser->can('waivers.approve') && ! in_array($item['status'], ['verified', 'waived'], true)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#waive-<?= esc((string) $item['id']) ?>">Waive</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($showUser !== null && $showUser->can('documents.reject')): ?>
                            <tr class="collapse" id="reject-<?= esc((string) $item['id']) ?>">
                                <td colspan="4">
                                    <form method="post" action="<?= esc(site_url('package-documents/' . $item['id'] . '/reject')) ?>" class="d-flex gap-2">
                                        <?= csrf_field() ?>
                                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Reason for rejection" required>
                                        <button type="submit" class="btn btn-sm btn-danger">Confirm Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($showUser !== null && $showUser->can('waivers.approve')): ?>
                            <tr class="collapse" id="waive-<?= esc((string) $item['id']) ?>">
                                <td colspan="4">
                                    <form method="post" action="<?= esc(site_url('package-documents/' . $item['id'] . '/waive')) ?>" class="d-flex gap-2">
                                        <?= csrf_field() ?>
                                        <input type="text" name="justification" class="form-control form-control-sm" placeholder="Justification for waiver" required>
                                        <button type="submit" class="btn btn-sm btn-secondary">Confirm Waive</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Workflow Actions</div>
    <div class="card-body d-flex flex-column gap-3">
        <?php if ($showUser !== null): ?>

            <?php if (in_array($status, ['draft', 'requirements_incomplete', 'returned_to_unit'], true) && $showUser->can('packages.submit_to_cdc')): ?>
                <form method="post" action="<?= esc(site_url('packages/' . $package['id'] . '/submit')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Submit to 1502 CDC</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'ready_for_cdc_validation' && $showUser->can('packages.validate_at_cdc')): ?>
                <form method="post" action="<?= esc(site_url('cdc/packages/' . $package['id'] . '/review')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Begin CDC Validation</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'under_cdc_validation'): ?>
                <?php if ($showUser->can('packages.validate_at_cdc')): ?>
                    <form method="post" action="<?= esc(site_url('cdc/packages/' . $package['id'] . '/validate')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success">Validate &amp; Proceed</button>
                    </form>
                <?php endif; ?>
                <?php if ($showUser->can('packages.return')): ?>
                    <form method="post" action="<?= esc(site_url('cdc/packages/' . $package['id'] . '/return')) ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="text" name="remarks" class="form-control" placeholder="Remarks (required)" required>
                        <button type="submit" class="btn btn-outline-danger text-nowrap">Return to Unit</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($status === 'cdc_validated' && $showUser->can('packages.submit_to_rcdg')): ?>
                <form method="post" action="<?= esc(site_url('cdc/packages/' . $package['id'] . '/submit-to-rcdg')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Submit to 15th RCDG</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'returned_by_rcdg' && $showUser->can('packages.submit_to_rcdg')): ?>
                <form method="post" action="<?= esc(site_url('cdc/packages/' . $package['id'] . '/resubmit-to-rcdg')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Resubmit to 15th RCDG</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($status, ['submitted_to_rcdg', 'resubmitted_to_rcdg'], true) && $showUser->can('packages.review_at_rcdg')): ?>
                <form method="post" action="<?= esc(site_url('rcdg/packages/' . $package['id'] . '/review')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Begin RCDG Review</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'under_rcdg_review'): ?>
                <?php if ($showUser->can('packages.endorse')): ?>
                    <form method="post" action="<?= esc(site_url('rcdg/packages/' . $package['id'] . '/endorse')) ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="text" name="remarks" class="form-control" placeholder="Endorsement remarks (optional)">
                        <button type="submit" class="btn btn-success text-nowrap">Endorse</button>
                    </form>
                <?php endif; ?>
                <?php if ($showUser->can('packages.return')): ?>
                    <form method="post" action="<?= esc(site_url('rcdg/packages/' . $package['id'] . '/return')) ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="text" name="remarks" class="form-control" placeholder="Remarks (required)" required>
                        <button type="submit" class="btn btn-outline-danger text-nowrap">Return to CDC</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($status === 'rcdg_endorsed' && $showUser->can('forwarding.record')): ?>
                <form method="post" action="<?= esc(site_url('records/packages/' . $package['id'] . '/forward')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-4"><input type="text" name="external_authority_name" class="form-control" placeholder="Approving authority name" required></div>
                    <div class="col-md-4"><input type="text" name="forwarding_reference_number" class="form-control" placeholder="Forwarding reference #" required></div>
                    <div class="col-md-3"><input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">Forward</button></div>
                </form>
            <?php endif; ?>

            <?php if ($status === 'awaiting_external_decision' && $showUser->can('external_decisions.record')): ?>
                <form method="post" action="<?= esc(site_url('records/packages/' . $package['id'] . '/decisions')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-2">
                        <select name="decision" class="form-select" required>
                            <option value="">Decision…</option>
                            <option value="approved">Approved</option>
                            <option value="disapproved">Disapproved</option>
                            <option value="deferred">Deferred</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="text" name="deciding_authority_name" class="form-control" placeholder="Deciding authority" required></div>
                    <div class="col-md-2"><input type="date" name="decision_date" class="form-control"></div>
                    <div class="col-md-2"><input type="text" name="decision_reference_number" class="form-control" placeholder="Reference #"></div>
                    <div class="col-md-2"><input type="text" name="remarks" class="form-control" placeholder="Remarks"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">Record</button></div>
                </form>
            <?php endif; ?>

            <?php if ($status === 'deferred' && $showUser->can('external_decisions.record')): ?>
                <form method="post" action="<?= esc(site_url('records/packages/' . $package['id'] . '/resume')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Resume (Decision Received)</button>
                </form>
            <?php endif; ?>

            <?php if ($status === 'approved' && $showUser->can('promotion_orders.record')): ?>
                <form method="post" action="<?= esc(site_url('records/packages/' . $package['id'] . '/promotion-order')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-3"><input type="date" name="order_date" class="form-control" placeholder="Order date"></div>
                    <div class="col-md-3"><input type="date" name="effective_date" class="form-control" placeholder="Effective date"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-success w-100">Record Promotion Order</button></div>
                </form>
            <?php endif; ?>

            <?php if (in_array($status, ['closed', 'archived', 'disapproved'], true)): ?>
                <p class="text-muted mb-0">This package has reached a terminal status; no further action is available.</p>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">Status History</div>
    <div class="card-body">
        <?php if (empty($history)): ?>
            <div class="empty-state"><i class="bi bi-clock-history"></i><p class="mb-0">No status changes recorded yet.</p></div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($history as $entry): ?>
                    <li class="list-group-item">
                        <strong><?= esc($entry['from_status'] !== null ? ucwords(str_replace('_', ' ', $entry['from_status'])) : 'Created') ?></strong>
                        &rarr;
                        <strong><?= esc(ucwords(str_replace('_', ' ', $entry['to_status']))) ?></strong>
                        <span class="text-muted ms-2"><?= esc($entry['created_at']) ?></span>
                        <?php if (! empty($entry['remarks'])): ?>
                            <div class="text-muted small">"<?= esc($entry['remarks']) ?>"</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
