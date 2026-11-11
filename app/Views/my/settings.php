<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Your sign-in details.</p>
    </div>
</div>

<?= view('components/account_settings', ['user' => $user, 'roles' => ['Reservist'], 'action' => site_url('my/settings/password')]) ?>
<?= $this->endSection() ?>
