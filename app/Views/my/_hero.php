<?php
/**
 * Welcome banner of the reservist portal: photo, name, role line, CDC and
 * company, over the soldier silhouette art.
 *
 * @var App\Entities\Reservist $reservist
 * @var array|null             $unit
 * @var array|null             $company
 * @var string|null            $photoUrl
 * @var string                 $subtitle
 */
$companyShort = $company !== null ? preg_replace('/,.*$/', '', (string) $company['unit_name']) : null;
$placeLine    = implode(' • ', array_filter([$unit['unit_name'] ?? null, $companyShort]));
?>
<section class="portal-hero">
    <div class="portal-avatar">
        <?php if ($photoUrl !== null): ?>
            <img src="<?= esc($photoUrl) ?>" alt="Photo of <?= esc($reservist->first_name) ?>">
        <?php else: ?>
            <i class="bi bi-person-fill" aria-hidden="true"></i>
        <?php endif; ?>
    </div>
    <div class="portal-hero-text">
        <h1>Welcome, <?= esc(trim($reservist->first_name . ' ' . $reservist->last_name)) ?>!</h1>
        <p class="portal-hero-role"><?= esc($subtitle) ?></p>
        <?php if ($placeLine !== ''): ?>
            <p class="portal-hero-place"><?= esc($placeLine) ?></p>
        <?php endif; ?>
    </div>
</section>
