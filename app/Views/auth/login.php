<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Sign in<?= $this->endSection() ?>
<?= $this->section('heroIcon') ?>bi-box-arrow-in-right<?= $this->endSection() ?>
<?= $this->section('heroTitle') ?>Philippine Army Reserve Force<?= $this->endSection() ?>
<?= $this->section('heroSubtitle') ?>Ready Reservists. Stronger Army.<?= $this->endSection() ?>

<?= $this->section('main') ?>

<?php // Layout per the client's login mock-up of 2026-11-10. The two photos
      // are cut from that mock-up (public/assets/img/login-*.jpg); every word
      // on the page is real text so it stays sharp and readable. ?>
<div class="login-stage">
    <div class="login-photo login-photo-left" aria-hidden="true"></div>
    <div class="login-photo login-photo-right" aria-hidden="true"></div>

    <div class="login-brand">
        <svg class="login-swoosh" viewBox="0 0 600 120" aria-hidden="true" focusable="false">
            <path d="M8 112 C120 18 330 -6 560 40" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"/>
            <path d="M540 14 L596 44 L534 64 Z" fill="currentColor"/>
        </svg>
        <p class="login-wordmark"><span class="wm-promo">Promo</span><span class="wm-track">Track</span></p>
        <p class="login-system">PARF Promotion Tracking System</p>
        <p class="login-tagline">From Application<br>to Approval &mdash; All in One System.</p>
    </div>

    <div class="auth-panel">
        <div class="auth-card-head">
            <i class="bi bi-person-fill" aria-hidden="true"></i>
            <h2>Welcome back</h2>
        </div>

        <?php // The intro gives way to a sign-in error so the card keeps its height. ?>
        <?php if (session('error') === null && session('errors') === null && session('message') === null) : ?>
            <?= view('auth/_panel', [
                'lede' => 'Track your promotions, monitor your application status, '
                    . 'and stay updated with the PARF advancement process.',
            ]) ?>
        <?php endif ?>

        <?= $this->include('auth/_alerts') ?>

        <form action="<?= url_to('login') ?>" method="post">
            <?= csrf_field() ?>

            <label class="auth-label" for="emailInput">Email Address</label>
            <div class="auth-field">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <input type="email" class="form-control" id="emailInput" name="email" inputmode="email"
                       autocomplete="email" placeholder="youremail@example.com"
                       value="<?= old('email') ?>" required>
            </div>

            <label class="auth-label" for="passwordInput">Password</label>
            <div class="auth-field">
                <i class="bi bi-lock" aria-hidden="true"></i>
                <input type="password" class="form-control" id="passwordInput" name="password"
                       autocomplete="current-password" placeholder="Enter your password" required>
                <button type="button" class="auth-reveal" data-reveal="passwordInput" aria-controls="passwordInput">SHOW</button>
            </div>

            <div class="auth-meta">
                <?php if (setting('Auth.sessionConfig')['allowRemembering']) : ?>
                    <div class="form-check m-0">
                        <input type="checkbox" name="remember" class="form-check-input" id="rememberInput" <?= old('remember') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="rememberInput"><?= lang('Auth.rememberMe') ?></label>
                    </div>
                <?php else : ?>
                    <span></span>
                <?php endif ?>

                <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                    <a href="<?= url_to('magic-link') ?>">Forgot Password?</a>
                <?php endif ?>
            </div>

            <button type="submit" class="auth-submit"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Sign In</button>

            <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                <div class="auth-divider">OR</div>
                <?php // The mock-up shows "Sign in with Google". There is no Google
                      // sign-in configured, so this is the login-link sign-in Shield has. ?>
                <a class="auth-alt" href="<?= url_to('magic-link') ?>"><i class="bi bi-envelope-paper me-1" aria-hidden="true"></i> Sign in with a login link</a>
            <?php endif ?>

            <?php if (setting('Auth.allowRegistration')) : ?>
                <p class="auth-foot"><?= lang('Auth.needAccount') ?> <a href="<?= url_to('register') ?>">Sign Up</a></p>
            <?php endif ?>
        </form>
    </div>

    <ol class="login-steps" aria-label="How a promotion moves">
        <li><span class="step-icon"><i class="bi bi-file-earmark-check"></i></span><span>Apply</span></li>
        <li><span class="step-icon"><i class="bi bi-search"></i></span><span>Review</span></li>
        <li><span class="step-icon"><i class="bi bi-people-fill"></i></span><span>Evaluate</span></li>
        <li class="is-final"><span class="step-icon"><i class="bi bi-check-lg"></i></span><span>Approve</span></li>
    </ol>

    <div class="login-footer" aria-hidden="true"><span>Serve &bull; Support &bull; Strengthen</span></div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<?= $this->include('auth/_reveal') ?>
<?= $this->endSection() ?>
