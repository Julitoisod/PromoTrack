<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Login link sent<?= $this->endSection() ?>
<?= $this->section('heroIcon') ?>bi-envelope-check<?= $this->endSection() ?>
<?= $this->section('heroTitle') ?><?= lang('Auth.useMagicLink') ?><?= $this->endSection() ?>
<?= $this->section('heroSubtitle') ?><?= lang('Auth.checkYourEmail') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="auth-panel">
    <?= view('auth/_panel', [
        'lede' => 'The link signs you in once and then expires. If it does, request another one '
            . 'from the login screen.',
    ]) ?>

    <p><?= lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60]) ?></p>

    <p class="auth-foot"><a href="<?= url_to('login') ?>"><?= lang('Auth.backToLogin') ?></a></p>
</div>

<?= $this->endSection() ?>
