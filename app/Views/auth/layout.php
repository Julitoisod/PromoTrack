<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $this->renderSection('title') ?> · PromoTrack</title>
    <link rel="icon" type="image/png" href="<?= esc(base_url('assets/img/logo-parf-96.png')) ?>">

    <?php // Overrides \CodeIgniter\Shield\Views\layout, which loads Bootstrap from a CDN.
          // Config/ContentSecurityPolicy restricts styles to 'self', so the CDN copy is
          // refused by the browser and the auth pages render unstyled.
          // auth.css replaces app.css here: the auth screens use none of the dashboard's
          // sidebar rules, and app.css offsets the body by the navbar height. ?>
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset_url('assets/css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= esc(asset_url('assets/css/auth.css')) ?>">

    <?= $this->renderSection('pageStyles') ?>
</head>

<?php
// The side menu marks the screen being shown. Magic-link screens belong to Login.
$authPath   = uri_string();
$onRegister = $authPath === 'register';
$onLogin    = $authPath === 'login';
?>
<body class="auth-page">

    <?php // Sign-up keeps the banner over its long form; the magic-link screens
          // put their card on the full-screen scene; login is the client's
          // photo layout of 2026-11-10. ?>
    <div class="auth-shell<?= $onRegister ? '' : ' auth-shell-full' ?><?= $onLogin ? ' auth-shell-login' : '' ?>">
        <aside class="auth-side" aria-label="PromoTrack">
            <a class="auth-brand" href="<?= esc(site_url('/')) ?>">
                <img src="<?= esc(base_url('assets/img/logo-parf.png')) ?>" alt="Philippine Army Reserve Force" width="128" height="110">
                <span class="auth-brand-name">PromoTrack</span>
                <span class="auth-brand-sub">PARF Promotion System</span>
            </a>

            <nav class="auth-nav">
                <?php if (setting('Auth.allowRegistration')) : ?>
                    <a href="<?= url_to('register') ?>" class="<?= $onRegister ? 'active' : '' ?>">
                        <i class="bi bi-person-plus-fill" aria-hidden="true"></i> Sign Up
                    </a>
                <?php endif ?>
                <a href="<?= url_to('login') ?>" class="<?= $onRegister ? '' : 'active' ?>">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Login
                </a>
            </nav>

            <div class="auth-side-art" aria-hidden="true"></div>
            <p class="auth-motto">&ldquo;Serve. Learn. Be Ready.&rdquo;</p>
        </aside>

        <main class="auth-main" role="main">
            <header class="auth-hero">
                <span class="auth-hero-icon" aria-hidden="true"><i class="bi <?= $this->renderSection('heroIcon') ?: 'bi-shield-check' ?>"></i></span>
                <div>
                    <h1><?= $this->renderSection('heroTitle') ?></h1>
                    <p><?= $this->renderSection('heroSubtitle') ?></p>
                </div>
            </header>

            <div class="auth-body">
                <?= $this->renderSection('main') ?>
            </div>
        </main>
    </div>

    <script src="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?= $this->renderSection('pageScripts') ?>
</body>
</html>
