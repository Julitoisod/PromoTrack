<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Login link<?= $this->endSection() ?>
<?= $this->section('heroIcon') ?>bi-envelope-paper<?= $this->endSection() ?>
<?= $this->section('heroTitle') ?><?= lang('Auth.useMagicLink') ?><?= $this->endSection() ?>
<?= $this->section('heroSubtitle') ?>Sign in without your password<?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="auth-panel">
    <?= view('auth/_panel', [
        'lede' => 'Forgot your password? We can email you a one-time link that signs you in '
            . 'without one, so you can set a new password from inside the system.',
    ]) ?>

    <?= $this->include('auth/_alerts') ?>

    <form action="<?= url_to('magic-link') ?>" method="post">
        <?= csrf_field() ?>

        <label class="auth-label" for="emailInput">Email Address</label>
        <div class="auth-field">
            <i class="bi bi-envelope-fill" aria-hidden="true"></i>
            <input type="email" class="form-control" id="emailInput" name="email" inputmode="email"
                   autocomplete="email" placeholder="youremail@example.com"
                   value="<?= old('email', auth()->user()->email ?? null) ?>" required>
        </div>

        <button type="submit" class="auth-submit mt-3"><?= lang('Auth.send') ?></button>

        <p class="auth-foot"><a href="<?= url_to('login') ?>"><?= lang('Auth.backToLogin') ?></a></p>
    </form>
</div>

<?= $this->endSection() ?>
