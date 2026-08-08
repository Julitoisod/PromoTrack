<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Reservists</h1>
    <?php $listUser = auth()->user(); ?>
    <?php if ($listUser !== null && $listUser->can('reservists.create')): ?>
        <a href="<?= esc(site_url('reservists/create')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Reservist
        </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($reservists)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p class="mb-0">No reservist profiles found yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="reservistsTable">
                    <thead>
                        <tr>
                            <th>Reservist #</th>
                            <th>Name</th>
                            <th>Rank</th>
                            <th>Unit</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservists as $reservist): ?>
                            <?php
                            $statusBadge = match ($reservist->status) {
                                'active'     => 'success',
                                'inactive'   => 'secondary',
                                'discharged' => 'warning',
                                'deceased'   => 'dark',
                                default      => 'secondary',
                            };
                            ?>
                            <tr>
                                <td><?= esc($reservist->reservist_number) ?></td>
                                <td><?= esc($reservist->getFullName()) ?></td>
                                <td><?= esc($reservist->rank_name ?? '—') ?></td>
                                <td><?= esc($reservist->unit_name ?? '—') ?></td>
                                <td><span class="badge text-bg-<?= esc($statusBadge) ?>"><?= esc(ucfirst($reservist->status ?? 'active')) ?></span></td>
                                <td class="text-end">
                                    <a href="<?= esc(site_url('reservists/' . $reservist->id)) ?>" class="btn btn-sm btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($listUser !== null && $listUser->can('reservists.update')): ?>
                                        <a href="<?= esc(site_url('reservists/' . $reservist->id . '/edit')) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="<?= esc(site_url('reservists/' . $reservist->id . '/delete')) ?>" method="post" class="d-inline" data-confirm="Remove this reservist profile?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
        if (window.jQuery && document.getElementById('reservistsTable')) {
            jQuery('#reservistsTable').DataTable({ order: [] });
        }
    });
</script>
<?= $this->endSection() ?>
