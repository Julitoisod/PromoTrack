<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Account Settings</h1>
        <p class="page-subtitle">Your sign-in details.</p>
    </div>
</div>

<?= view('components/account_settings', ['user' => $user, 'roles' => $roles, 'action' => site_url('account/settings/password')]) ?>
<?= $this->endSection() ?>
