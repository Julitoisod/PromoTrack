<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Login link sent<?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="auth-card">
    <div class="row g-0">

        <?= view('auth/_panel', [
            'lede' => 'The link signs you in once and then expires. If it does, request another one '
                . 'from the login screen.',
        ]) ?>

        <div class="col-12 col-md-7 auth-form">
            <h1><?= lang('Auth.useMagicLink') ?></h1>
            <p class="auth-lede"><b><?= lang('Auth.checkYourEmail') ?></b></p>
            <p><?= lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60]) ?></p>

            <p class="auth-foot"><a href="<?= url_to('login') ?>"><?= lang('Auth.backToLogin') ?></a></p>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
