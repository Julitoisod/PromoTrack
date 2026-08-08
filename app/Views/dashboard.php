<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $dashUser = auth()->user(); ?>
<h1 class="h3 mb-4">Welcome, <?= esc($dashUser->username ?? $dashUser->email ?? 'User') ?></h1>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Eligible for Promotion</div>
                <div class="h3 mb-0 text-success"><?= esc((string) $eligibleCount) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Overdue Promotions</div>
                <div class="h3 mb-0 text-danger"><?= esc((string) $overdueCount) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pending Packages</div>
                <div class="h3 mb-0 text-warning"><?= esc((string) $pendingPackages) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Promotions Issued</div>
                <div class="h3 mb-0 text-primary"><?= esc((string) $promotionsIssued) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Promotion Package Pipeline</div>
            <div class="card-body">
                <?php if (empty($packageCounts)): ?>
                    <p class="text-muted mb-0">No promotion packages have been created yet.</p>
                <?php else: ?>
                    <canvas id="packagePipelineChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Quick Links</div>
            <div class="card-body d-flex flex-column gap-2">
                <?php if ($dashUser !== null && $dashUser->can('reservists.view')): ?>
                    <a href="<?= esc(site_url('reservists')) ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-people me-1"></i> View Reservists
                    </a>
                    <a href="<?= esc(site_url('packages')) ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-folder2-open me-1"></i> View Promotion Packages
                    </a>
                <?php endif; ?>
                <?php if ($dashUser !== null && $dashUser->can('reports.view')): ?>
                    <a href="<?= esc(site_url('reports/backlog')) ?>" class="btn btn-outline-secondary text-start">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Promotion Backlog Report
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var canvas = document.getElementById('packagePipelineChart');

        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var labels = <?= json_encode(array_map(static fn ($status) => ucwords(str_replace('_', ' ', $status)), array_keys($packageCounts))) ?>;
        var data = <?= json_encode(array_values($packageCounts)) ?>;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Packages',
                    data: data,
                    backgroundColor: '#0d6efd',
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    });
</script>
<?= $this->endSection() ?>
