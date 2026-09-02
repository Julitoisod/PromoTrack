<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Login link<?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="auth-card">
    <div class="row g-0">

        <?= view('auth/_panel', [
            'lede' => 'Forgot your password? We can email you a one-time link that signs you in '
                . 'without one, so you can set a new password from inside the system.',
        ]) ?>

        <div class="col-12 col-md-7 auth-form">
            <h1><?= lang('Auth.useMagicLink') ?></h1>
            <p class="auth-lede">Enter your registered email address and we will send you a sign-in link.</p>

            <?= $this->include('auth/_alerts') ?>

            <form action="<?= url_to('magic-link') ?>" method="post">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                    <input type="email" class="form-control" id="emailInput" name="email" inputmode="email"
                           autocomplete="email" placeholder="Email Address" aria-label="Email Address"
                           value="<?= old('email', auth()->user()->email ?? null) ?>" required>
                </div>

                <button type="submit" class="auth-submit mt-3"><?= lang('Auth.send') ?></button>

                <p class="auth-foot"><a href="<?= url_to('login') ?>"><?= lang('Auth.backToLogin') ?></a></p>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
