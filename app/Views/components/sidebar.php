<?php
/**
 * Left navigation sidebar. Included by layouts/main.php.
 *
 * Menu items are gated by CodeIgniter Shield permissions so a user only
 * ever sees links they are actually authorized to open. Modules not yet
 * implemented in the current phase are rendered as disabled "Soon"
 * placeholders so the full information architecture from the master
 * spec is visible without producing dead/broken links.
 */
$sidebarUser   = auth()->user();
$currentPath   = uri_string();
$can           = static fn (string $permission): bool => $sidebarUser !== null && $sidebarUser->can($permission);
$isActive      = static fn (string $path): string => $currentPath === $path ? 'active' : '';
?>
<aside class="sidebar bg-light border-end" id="appSidebar">
    <div class="sidebar-inner p-3">
        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link <?= $isActive('') ?>" href="<?= esc(site_url('/')) ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>

            <?php if ($can('reservists.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive('reservists') ?>" href="<?= esc(site_url('reservists')) ?>">
                        <i class="bi bi-people me-2"></i> Reservists
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-item mt-3">
                <span class="text-uppercase text-muted small fw-semibold px-2">Promotion Management</span>
            </li>
            <?php if ($can('reservists.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive('packages') ?>" href="<?= esc(site_url('packages')) ?>">
                        <i class="bi bi-folder2-open me-2"></i> Promotion Packages
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($can('packages.validate_at_cdc')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= esc(site_url('packages?status=ready_for_cdc_validation')) ?>">
                        <i class="bi bi-building-check me-2"></i> CDC Validation Queue
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($can('packages.review_at_rcdg')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= esc(site_url('packages?status=submitted_to_rcdg')) ?>">
                        <i class="bi bi-diagram-3 me-2"></i> RCDG Review Inbox
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($can('forwarding.record')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= esc(site_url('packages?status=rcdg_endorsed')) ?>">
                        <i class="bi bi-send-check me-2"></i> External Forwarding
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($can('promotion_orders.record')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= esc(site_url('packages?status=approved')) ?>">
                        <i class="bi bi-award me-2"></i> Promotion Orders
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('reports.generate')): ?>
                <li class="nav-item mt-3">
                    <span class="text-uppercase text-muted small fw-semibold px-2">Reports</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= esc(site_url('reports/backlog')) ?>" target="_blank">
                        <i class="bi bi-file-earmark-bar-graph me-2"></i> Promotion Backlog Report
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= esc(site_url('reports/backlog.csv')) ?>">
                        <i class="bi bi-filetype-csv me-2"></i> Backlog Spreadsheet
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('users.manage') || $can('rules.manage') || $can('units.manage') || $can('settings.manage') || $can('audit_logs.view')): ?>
                <li class="nav-item mt-3">
                    <span class="text-uppercase text-muted small fw-semibold px-2">Administration</span>
                </li>
                <?php
                // `path` null means the module is still unbuilt and renders as
                // a disabled placeholder rather than a dead link.
                foreach ([
                    ['perm' => 'units.manage', 'icon' => 'bi-diagram-2', 'label' => 'Reference Data', 'path' => 'admin/reference'],
                    ['perm' => 'rules.manage', 'icon' => 'bi-journal-check', 'label' => 'Promotion Rules', 'path' => 'admin/rules'],
                    ['perm' => 'audit_logs.view', 'icon' => 'bi-shield-lock', 'label' => 'Audit Logs', 'path' => 'admin/audit-logs'],
                    ['perm' => 'settings.manage', 'icon' => 'bi-gear', 'label' => 'Settings & Backups', 'path' => 'admin/settings'],
                    ['perm' => 'users.manage', 'icon' => 'bi-person-gear', 'label' => 'Users', 'path' => null],
                ] as $adminItem): ?>
                    <?php if ($can($adminItem['perm'])): ?>
                        <li class="nav-item">
                            <?php if ($adminItem['path'] === null): ?>
                                <span class="nav-link disabled d-flex justify-content-between align-items-center">
                                    <span><i class="bi <?= esc($adminItem['icon']) ?> me-2"></i> <?= esc($adminItem['label']) ?></span>
                                    <span class="badge text-bg-secondary">Soon</span>
                                </span>
                            <?php else: ?>
                                <a class="nav-link <?= $isActive($adminItem['path']) ?>" href="<?= esc(site_url($adminItem['path'])) ?>">
                                    <i class="bi <?= esc($adminItem['icon']) ?> me-2"></i> <?= esc($adminItem['label']) ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</aside>
