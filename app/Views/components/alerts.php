<?php
/**
 * Renders dismissible Bootstrap alerts for every flash message type used
 * across PromoTrack AFP-RF controllers:
 *   ->with('success', 'message')
 *   ->with('error', 'message')
 *   ->with('warning', 'message')
 *   ->with('info', 'message')
 *
 * Included once near the top of the main content block by layouts/main.php.
 */
$flashTypes = [
    'success' => 'success',
    'error'   => 'danger',
    'warning' => 'warning',
    'info'    => 'info',
];
?>
<?php foreach ($flashTypes as $flashKey => $bsClass): ?>
    <?php $flashMessage = session()->getFlashdata($flashKey); ?>
    <?php if (! empty($flashMessage)): ?>
        <div class="alert alert-<?= esc($bsClass) ?> alert-dismissible fade show" role="alert">
            <?= esc($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
