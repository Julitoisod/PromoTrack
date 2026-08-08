<?php
/**
 * Top navigation bar. Included by layouts/main.php for every
 * authenticated page.
 */
$navUser = auth()->user();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3 fixed-top">
    <button class="btn btn-outline-light d-lg-none me-2" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand fw-semibold" href="<?= esc(site_url('/')) ?>">
        <i class="bi bi-shield-check me-1"></i> PromoTrack AFP-RF
    </a>

    <div class="ms-auto d-flex align-items-center">
        <?php if ($navUser !== null): ?>
            <div class="dropdown me-2">
                <button class="btn btn-dark position-relative" type="button" id="notificationsToggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none" id="notificationsBadge">0</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notifications-menu" id="notificationsList" aria-labelledby="notificationsToggle">
                    <li><span class="dropdown-item-text text-muted small">No new notifications.</span></li>
                </ul>
            </div>
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle d-flex align-items-center" type="button" id="userMenuToggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>
                    <span><?= esc($navUser->username ?? $navUser->email ?? 'Account') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuToggle">
                    <li><span class="dropdown-item-text text-muted small">
                        <?= esc(implode(', ', $navUser->getGroups())) ?>
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= esc(route_to('logout')) ?>">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        <?php else: ?>
            <a class="btn btn-outline-light btn-sm" href="<?= esc(route_to('login')) ?>">Login</a>
        <?php endif; ?>
    </div>
</nav>
