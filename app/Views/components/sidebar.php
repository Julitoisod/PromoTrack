<?php
/**
 * Left navigation sidebar. Included by layouts/main.php.
 *
 * The menu is a data structure rather than repeated markup: every entry is
 * gated by a Shield permission so a user only ever sees links they are
 * authorized to open, and an entry with a null `path` is a module that is not
 * built yet, rendered as a disabled "Soon" placeholder so the information
 * architecture from the master spec stays visible without dead links.
 *
 * Groups collapse with Bootstrap's own Collapse plugin (already vendored) and
 * open automatically when they contain the page being viewed.
 */
$sidebarUser = auth()->user();
$currentPath = uri_string();
$can         = static fn (?string $permission): bool => $permission === null
    || ($sidebarUser !== null && $sidebarUser->can($permission));

// A reservist sees their own portal only. The staff menu below is not
// merely hidden from them by permissions -- the dashboard entry carries no
// permission at all, and its tiles count every package in the organization.
$isReservist = $sidebarUser !== null && $sidebarUser->inGroup('reservist');

if ($isReservist) {
    echo view('components/sidebar_reservist', ['currentPath' => $currentPath]);

    return;
}

$menu = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'path' => '', 'perm' => null],
    ['icon' => 'bi-people', 'label' => 'Reservists', 'path' => 'reservists', 'perm' => 'reservists.view'],

    ['group' => 'Promotion Management', 'id' => 'navPromotion', 'items' => [
        ['icon' => 'bi-folder2-open', 'label' => 'Promotion Packages', 'path' => 'packages', 'perm' => 'reservists.view'],
        ['icon' => 'bi-building-check', 'label' => 'CDC Validation Queue', 'path' => 'packages?status=ready_for_cdc_validation', 'perm' => 'packages.validate_at_cdc'],
        ['icon' => 'bi-diagram-3', 'label' => 'RCDG Review Inbox', 'path' => 'packages?status=submitted_to_rcdg', 'perm' => 'packages.review_at_rcdg'],
        ['icon' => 'bi-send-check', 'label' => 'External Forwarding', 'path' => 'packages?status=rcdg_endorsed', 'perm' => 'forwarding.record'],
        ['icon' => 'bi-award', 'label' => 'Promotion Orders', 'path' => 'packages?status=approved', 'perm' => 'promotion_orders.record'],
    ]],

    ['group' => 'Reports', 'id' => 'navReports', 'items' => [
        ['icon' => 'bi-file-earmark-bar-graph', 'label' => 'Promotion Backlog Report', 'path' => 'reports/backlog', 'perm' => 'reports.generate', 'blank' => true],
        ['icon' => 'bi-filetype-csv', 'label' => 'Backlog Spreadsheet', 'path' => 'reports/backlog.csv', 'perm' => 'reports.generate'],
    ]],

    ['group' => 'Administration', 'id' => 'navAdmin', 'items' => [
        ['icon' => 'bi-diagram-2', 'label' => 'Reference Data', 'path' => 'admin/reference', 'perm' => 'units.manage'],
        ['icon' => 'bi-journal-check', 'label' => 'Promotion Rules', 'path' => 'admin/rules', 'perm' => 'rules.manage'],
        ['icon' => 'bi-shield-lock', 'label' => 'Audit Logs', 'path' => 'admin/audit-logs', 'perm' => 'audit_logs.view'],
        ['icon' => 'bi-gear', 'label' => 'Settings & Backups', 'path' => 'admin/settings', 'perm' => 'settings.manage'],
        ['icon' => 'bi-person-gear', 'label' => 'Users', 'path' => null, 'perm' => 'users.manage'],
    ]],
];

// Several entries point at the same controller with a different `status`
// filter, so the comparison has to include the query string: matching on the
// path alone lights up all five Promotion Management links at once.
$currentQuery = service('request')->getUri()->getQuery();
$currentUri   = $currentQuery === '' ? $currentPath : $currentPath . '?' . $currentQuery;

$isCurrent = static function (array $item) use ($currentPath, $currentUri): bool {
    if ($item['path'] === null) {
        return false;
    }

    if ($item['path'] === $currentUri) {
        return true;
    }

    // A child page keeps its parent lit: /reservists/12/edit belongs to
    // Reservists. Entries carrying a query string are excluded, or every
    // status filter would match its own base path, and so is the dashboard,
    // whose empty path is a prefix of everything.
    return $item['path'] !== ''
        && ! str_contains((string) $item['path'], '?')
        && str_starts_with($currentPath, $item['path'] . '/');
};
?>
<aside class="sidebar" id="appSidebar">
    <nav class="sidebar-inner" aria-label="Main">
        <ul class="nav flex-column gap-1">
            <?php foreach ($menu as $entry): ?>
                <?php if (! isset($entry['group'])): ?>
                    <?php if ($can($entry['perm'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $isCurrent($entry) ? 'active' : '' ?>"
                               href="<?= esc(site_url($entry['path'])) ?>" title="<?= esc($entry['label']) ?>">
                                <i class="bi <?= esc($entry['icon']) ?>"></i>
                                <span class="nav-text"><?= esc($entry['label']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php continue; ?>
                <?php endif; ?>

                <?php
                $items = array_values(array_filter(
                    $entry['items'],
                    static fn (array $item): bool => $can($item['perm']),
                ));

                if ($items === []) {
                    continue;
                }

                // Keep a group expanded while it contains the page being viewed.
                $open = array_filter($items, $isCurrent) !== [];
                ?>
                <li class="nav-item nav-group">
                    <button class="nav-group-toggle <?= $open ? '' : 'collapsed' ?>" type="button"
                            data-bs-toggle="collapse" data-bs-target="#<?= esc($entry['id']) ?>"
                            aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= esc($entry['id']) ?>">
                        <span><?= esc($entry['group']) ?></span>
                        <i class="bi bi-chevron-right nav-chevron"></i>
                    </button>
                    <ul class="nav flex-column gap-1 collapse <?= $open ? 'show' : '' ?>" id="<?= esc($entry['id']) ?>">
                        <?php foreach ($items as $item): ?>
                            <li class="nav-item">
                                <?php if ($item['path'] === null): ?>
                                    <span class="nav-link disabled" title="<?= esc($item['label']) ?>">
                                        <i class="bi <?= esc($item['icon']) ?>"></i>
                                        <span class="nav-text"><?= esc($item['label']) ?></span>
                                        <span class="badge text-bg-secondary ms-auto">Soon</span>
                                    </span>
                                <?php else: ?>
                                    <a class="nav-link <?= $isCurrent($item) ? 'active' : '' ?>"
                                       href="<?= esc(site_url($item['path'])) ?>" title="<?= esc($item['label']) ?>"
                                       <?= ($item['blank'] ?? false) ? 'target="_blank" rel="noopener"' : '' ?>>
                                        <i class="bi <?= esc($item['icon']) ?>"></i>
                                        <span class="nav-text"><?= esc($item['label']) ?></span>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
