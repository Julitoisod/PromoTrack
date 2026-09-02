<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $dashUser = auth()->user(); ?>
<h1 class="page-title">Welcome, <?= esc($dashUser->username ?? $dashUser->email ?? 'User') ?></h1>

<div class="row g-3 mb-3">
    <?php // One tile per headline figure; the four differ only by label, value and accent.
    foreach ([
        ['label' => 'Eligible for Promotion', 'value' => $eligibleCount,    'icon' => 'bi-person-check', 'tone' => 'success'],
        ['label' => 'Overdue Promotions',     'value' => $overdueCount,     'icon' => 'bi-exclamation-triangle', 'tone' => 'danger'],
        ['label' => 'Pending Packages',       'value' => $pendingPackages,  'icon' => 'bi-hourglass-split', 'tone' => 'warning'],
        ['label' => 'Promotions Issued',      'value' => $promotionsIssued, 'icon' => 'bi-award', 'tone' => 'primary'],
    ] as $tile): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="stat-icon text-<?= esc($tile['tone']) ?> bg-<?= esc($tile['tone']) ?>-subtle">
                        <i class="bi <?= esc($tile['icon']) ?>"></i>
                    </span>
                    <div>
                        <div class="stat-label"><?= esc($tile['label']) ?></div>
                        <div class="stat-value text-<?= esc($tile['tone']) ?>"><?= esc((string) $tile['value']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
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
        <div class="card h-100">
            <div class="card-header">Promotions Issued (last 12 months)</div>
            <div class="card-body">
                <?php if (array_sum($promotionsByMonth) === 0): ?>
                    <p class="text-muted mb-0">No promotion orders have been recorded yet.</p>
                <?php else: ?>
                    <canvas id="promotionsTrendChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Average Days in Stage</div>
            <div class="card-body">
                <?php if ($averageDaysInStatus === []): ?>
                    <p class="text-muted mb-0">No package has completed a workflow stage yet.</p>
                <?php else: ?>
                    <canvas id="stageDurationChart" height="220"></canvas>
                    <p class="text-muted small mb-0 mt-2">
                        Counted only for stages a package has actually left, so a package still
                        sitting in a stage does not flatter the average.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
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
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Promotion Backlog Report (PDF)
                    </a>
                    <a href="<?= esc(site_url('reports/backlog.csv')) ?>" class="btn btn-outline-secondary text-start">
                        <i class="bi bi-filetype-csv me-1"></i> Promotion Backlog (Spreadsheet)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
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

    document.addEventListener('DOMContentLoaded', function () {
        var trendCanvas = document.getElementById('promotionsTrendChart');

        if (trendCanvas && typeof Chart !== 'undefined') {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_keys($promotionsByMonth)) ?>,
                    datasets: [{
                        label: 'Promotions issued',
                        data: <?= json_encode(array_values($promotionsByMonth)) ?>,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.12)',
                        fill: true,
                        tension: 0.25,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        }

        var stageCanvas = document.getElementById('stageDurationChart');

        if (stageCanvas && typeof Chart !== 'undefined') {
            new Chart(stageCanvas, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_map(
                        static fn ($status) => ucwords(str_replace('_', ' ', $status)),
                        array_keys($averageDaysInStatus)
                    )) ?>,
                    datasets: [{
                        label: 'Average days',
                        data: <?= json_encode(array_values($averageDaysInStatus)) ?>,
                        backgroundColor: '#fd7e14',
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } },
                },
            });
        }
    });
</script>
<?= $this->endSection() ?>
