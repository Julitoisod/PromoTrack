<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-title">Reservists</h1>
    <?php $listUser = auth()->user(); ?>
    <?php if ($listUser !== null && $listUser->can('reservists.create')): ?>
        <a href="<?= esc(site_url('reservists/create')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Reservist
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= esc(site_url('reservists')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q"
                       placeholder="Name, reservist number, or serial number"
                       value="<?= esc($search) ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
            <?php if ($search !== ''): ?>
                <div class="col-md-2 d-grid">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= esc(site_url('reservists')) ?>">Clear</a>
                </div>
            <?php endif; ?>
        </form>

        <?php if (empty($reservists)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p class="mb-0">
                    <?= $search !== '' ? 'No reservist matches that search.' : 'No reservist profiles found yet.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="reservistsTable">
                    <thead>
                        <tr>
                            <th scope="col">Reservist #</th>
                            <th scope="col">Name</th>
                            <th scope="col">Rank</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Status</th>
                            <th class="text-end" scope="col">Actions</th>
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

            <?= $pager->links() ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
    // Sorting only -- paging and searching are done in SQL (see
    // ReservistsController::index), so DataTables must not add its own.
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && document.getElementById('reservistsTable')) {
            jQuery('#reservistsTable').DataTable({
                order: [],
                paging: false,
                searching: false,
                info: false,
            });
        }
    });
</script>
<?= $this->endSection() ?>
