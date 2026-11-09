<?php
/**
 * "My Information" card: the reservist's key record fields with an Edit link.
 *
 * @var App\Entities\Reservist $reservist
 * @var array|null             $unit
 * @var array|null             $company
 * @var array|null             $rank
 */
$infoRows = [
    'Full Name'              => trim($reservist->first_name . ' '
        . ($reservist->middle_name ? mb_substr($reservist->middle_name, 0, 1) . '. ' : '')
        . $reservist->last_name . ($reservist->suffix ? ' ' . $reservist->suffix : '')),
    'Serial Number'          => $reservist->afp_serial_number ?? '—',
    'CDC'                    => $unit['unit_name'] ?? '—',
    'Company'                => $company['unit_name'] ?? '—',
    'Rank on Record'         => $rank['rank_name'] ?? '—',
    'Educational Attainment' => $reservist->getAttainmentLabel() ?? '—',
];
?>
<div class="card portal-card h-100">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>My Information</span>
        <a href="<?= esc(site_url('my/profile')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil-square me-1"></i> Edit
        </a>
    </div>
    <div class="card-body">
        <dl class="info-list mb-0">
            <?php foreach ($infoRows as $label => $value): ?>
                <dt><?= esc($label) ?></dt>
                <dd><?= esc((string) $value) ?></dd>
            <?php endforeach; ?>
        </dl>
    </div>
</div>
