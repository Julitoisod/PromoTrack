<?php
/**
 * The reservist portal's side menu, per the client's dashboard mock-up of
 * 2026-11-09: crest, the six screens, and Logout at the foot. Included by
 * components/sidebar.php for accounts in the `reservist` group.
 *
 * @var string $currentPath
 */
$portalMenu = [
    ['icon' => 'bi-house-door-fill', 'label' => 'Dashboard', 'path' => 'my/dashboard'],
    ['icon' => 'bi-person-fill', 'label' => 'My Profile', 'path' => 'my/profile'],
    ['icon' => 'bi-bar-chart-steps', 'label' => 'ROTC Progress', 'path' => 'my/rotc'],
    ['icon' => 'bi-file-earmark-text-fill', 'label' => 'My Documents', 'path' => 'my/promotion'],
    ['icon' => 'bi-bell-fill', 'label' => 'Notifications', 'path' => 'my/notifications'],
    ['icon' => 'bi-gear-fill', 'label' => 'Settings', 'path' => 'my/settings'],
];
?>
<aside class="sidebar sidebar-portal" id="appSidebar">
    <div class="portal-brand">
        <img src="<?= esc(base_url('assets/img/logo-parf.png')) ?>" alt="Philippine Army Reserve Force" width="104" height="89">
        <span class="portal-brand-name">PromoTrack</span>
        <span class="portal-brand-sub">PARF Promotion System</span>
    </div>

    <nav aria-label="Main">
        <ul class="nav flex-column gap-1">
            <?php foreach ($portalMenu as $item): ?>
                <?php $active = $currentPath === $item['path'] || str_starts_with($currentPath, $item['path'] . '/'); ?>
                <li class="nav-item">
                    <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= esc(site_url($item['path'])) ?>"
                       title="<?= esc($item['label']) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= esc($item['icon']) ?>"></i>
                        <span class="nav-text"><?= esc($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <a class="nav-link portal-logout" href="<?= esc(route_to('logout')) ?>" title="Logout">
        <i class="bi bi-box-arrow-left"></i>
        <span class="nav-text">Logout</span>
    </a>
</aside>
