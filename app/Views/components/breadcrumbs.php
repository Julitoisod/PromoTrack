<?php
/**
 * Breadcrumb component.
 *
 * Expects an optional `$breadcrumbs` array passed from the calling view,
 * shaped as `['Label' => 'url/or/empty/for/current', ...]`. The last item
 * is always rendered as the active (non-linked) crumb regardless of
 * whether a URL was supplied for it.
 *
 * Example (in a view, before extending the layout or via view data):
 *   $breadcrumbs = [
 *       'Dashboard'  => site_url('/'),
 *       'Reservists' => site_url('reservists'),
 *       'View'       => '',
 *   ];
 */
$breadcrumbs ??= [];
?>
<?php if (! empty($breadcrumbs)): ?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-3">
            <?php $labels = array_keys($breadcrumbs); ?>
            <?php $lastLabel = end($labels); ?>
            <?php foreach ($breadcrumbs as $label => $url): ?>
                <?php if ($label === $lastLabel): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= esc($label) ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="<?= esc($url) ?>"><?= esc($label) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>
