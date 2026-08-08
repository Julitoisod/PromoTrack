<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Promotion Packages</h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($packages)): ?>
            <div class="empty-state">
                <i class="bi bi-folder2-open"></i>
                <p class="mb-0">No promotion packages found yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="packagesTable">
                    <thead>
                        <tr>
                            <th>Package #</th>
                            <th>Reservist</th>
                            <th>Unit</th>
                            <th>Status</th>
                            <th>Completeness</th>
                            <th class="text-end">Actions</th>
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
                                <td><span class="badge text-bg-<?= esc($statusBadge) ?>"><?= esc($statusLabel) ?></span></td>
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
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && document.getElementById('packagesTable')) {
            jQuery('#packagesTable').DataTable({ order: [] });
        }
    });
</script>
<?= $this->endSection() ?>
