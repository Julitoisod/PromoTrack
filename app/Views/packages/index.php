<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <?php // Titled after the side-menu link that opened it (see
          // PromotionPackagesController::index), which also stays highlighted. ?>
    <h1 class="page-title"><?= esc($title) ?></h1>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($packages)): ?>
            <div class="empty-state">
                <i class="bi bi-folder2-open"></i>
                <p class="mb-0">
                    <?= $currentStatus !== null
                        ? 'No packages are at this stage right now.'
                        : 'No promotion packages found yet.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="packagesTable">
                    <thead>
                        <tr>
                            <th scope="col">Package #</th>
                            <th scope="col">Reservist</th>
                            <th scope="col">Unit</th>
                            <th class="text-center" scope="col">Status</th>
                            <th scope="col">Completeness</th>
                            <th class="text-end" scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $package): ?>
                            <?php
                            $statusBadge = match ($package['status']) {
                                'closed', 'promotion_order_recorded' => 'success',
                                'returned_to_unit', 'returned_by_rcdg', 'disapproved' => 'danger',
                                'requirements_incomplete'                            => 'warning',
                                default                                              => 'secondary',
                            };
                            $statusLabel = ucwords(str_replace('_', ' ', $package['status']));
                            ?>
                            <tr>
                                <td><?= esc($package['package_number']) ?></td>
                                <td><?= esc($package['last_name'] . ', ' . $package['first_name']) ?> (<?= esc($package['reservist_number']) ?>)</td>
                                <td><?= esc($package['unit_name'] ?? '—') ?></td>
                                <td class="text-center"><span class="badge text-bg-<?= esc($statusBadge) ?>"><?= esc($statusLabel) ?></span></td>
                                <td><?= esc((string) $package['completeness_percentage']) ?>%</td>
                                <td class="text-end">
                                    <a href="<?= esc(site_url('packages/' . $package['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $pager->links() ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
    // Sorting only -- paging is done in SQL (see PromotionPackagesController::index).
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && document.getElementById('packagesTable')) {
            jQuery('#packagesTable').DataTable({
                order: [],
                paging: false,
                searching: false,
                info: false,
            });
        }
    });
</script>
<?= $this->endSection() ?>
