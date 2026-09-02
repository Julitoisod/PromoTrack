<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Sign in<?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="auth-card">
    <div class="row g-0">

        <?= view('auth/_panel', [
            'lede' => 'Promotion records and workflow for Reserve Force unit staff. '
                . 'Sign in with the account issued by your S1 or system administrator.',
        ]) ?>

        <div class="col-12 col-md-7 auth-form">
            <h1>Sign in</h1>
            <p class="auth-lede">Use your registered email address and password.</p>

            <?= $this->include('auth/_alerts') ?>

            <form action="<?= url_to('login') ?>" method="post">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                    <input type="email" class="form-control" id="emailInput" name="email" inputmode="email"
                           autocomplete="email" placeholder="Email Address" aria-label="Email Address"
                           value="<?= old('email') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <input type="password" class="form-control" id="passwordInput" name="password"
                           autocomplete="current-password" placeholder="Password" aria-label="Password" required>
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

                <button type="submit" class="auth-submit">Sign in</button>

                <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                    <div class="auth-divider">Or</div>
                    <?php // The reference layout's second button is a social sign-in. There is no
                          // identity provider on the intranet deployment, so it points at the
                          // magic-link login Shield already provides. ?>
                    <a class="auth-alt" href="<?= url_to('magic-link') ?>">Sign in with a login link</a>
                <?php endif ?>

                <?php if (setting('Auth.allowRegistration')) : ?>
                    <p class="auth-foot"><?= lang('Auth.needAccount') ?> <a href="<?= url_to('register') ?>">Sign Up</a></p>
                <?php endif ?>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<?= $this->include('auth/_reveal') ?>
<?= $this->endSection() ?>
