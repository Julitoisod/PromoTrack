<?php
/**
 * The thirteen-item (A-M) promotion document checklist.
 *
 * Shared by the staff package view and the reservist's own promotion
 * page so the two can never disagree about which actions an item
 * offers. Every action is gated on a Shield permission, so the same
 * markup renders an upload button for the reservist who owns the
 * package and verify/reject buttons for the S1 clerk or CDC reviewing
 * it, without either view knowing about the other's role.
 *
 * Expected variables:
 *   $checklist  array of package_documents rows joined to their requirement.
 *   $status     the parent package's workflow status.
 */
$checklistUser = auth()->user();

// Matches PackageDocumentsController::UPLOADABLE_PACKAGE_STATUSES: once a
// package has left the unit its documents are frozen, and the controller
// refuses an upload posted anyway.
$acceptsUploads = in_array($status, ['draft', 'requirements_incomplete', 'returned_to_unit', 'returned_by_rcdg'], true);

// A finished package is a finished record: verify/reject/waive are hidden
// here and refused by PackageDocumentsController::refuseIfPackageIsClosed(),
// so the buttons and the server agree. Download stays available -- reading
// a closed package's documents is exactly what it is kept for.
$acceptsReview = ! in_array($status, ['disapproved', 'closed', 'archived'], true);
?>
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

                        <?php if ($checklistUser !== null && $checklistUser->can('documents.upload') && $acceptsUploads): ?>
                            <form method="post" action="<?= esc(site_url('package-documents/' . $item['id'] . '/upload')) ?>" enctype="multipart/form-data" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="file" name="document" class="form-control form-control-sm d-inline-block w-auto" required>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <?= $item['current_version_id'] !== null ? 'Replace' : 'Upload' ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($checklistUser !== null && $acceptsReview && $item['status'] === 'uploaded'): ?>
                            <?php if ($checklistUser->can('documents.verify')): ?>
                                <form method="post" action="<?= esc(site_url('package-documents/' . $item['id'] . '/verify')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Verify</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($checklistUser->can('documents.reject')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-<?= esc((string) $item['id']) ?>">Reject</button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($checklistUser !== null && $acceptsReview && $checklistUser->can('waivers.approve') && ! in_array($item['status'], ['verified', 'waived'], true)): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#waive-<?= esc((string) $item['id']) ?>">Waive</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($checklistUser !== null && $acceptsReview && $checklistUser->can('documents.reject')): ?>
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
                <?php if ($checklistUser !== null && $acceptsReview && $checklistUser->can('waivers.approve')): ?>
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
