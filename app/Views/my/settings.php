<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var CodeIgniter\Shield\Entities\User $user
 */
$errors  = session()->getFlashdata('errors') ?? [];
$invalid = static fn (string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Your sign-in details.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card portal-card h-100">
            <div class="card-header">Account</div>
            <div class="card-body">
                <dl class="info-list mb-0">
                    <dt>Username</dt><dd><?= esc($user->username ?? '—') ?></dd>
                    <dt>Email</dt><dd><?= esc($user->email ?? '—') ?></dd>
                    <dt>Role</dt><dd>Reservist</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <form method="post" action="<?= esc(site_url('my/settings/password')) ?>" class="card portal-card">
            <?= csrf_field() ?>
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input type="password" class="form-control<?= $invalid('current_password') ?>" id="current_password" name="current_password" autocomplete="current-password" required>
                    <?= view('components/form_errors', ['field' => 'current_password']) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">New Password</label>
                    <input type="password" class="form-control<?= $invalid('password') ?>" id="password" name="password" autocomplete="new-password" required>
                    <?= view('components/form_errors', ['field' => 'password']) ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="password_confirm">Repeat New Password</label>
                    <input type="password" class="form-control<?= $invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    <?= view('components/form_errors', ['field' => 'password_confirm']) ?>
                </div>
                <p class="form-text mb-0">At least 8 characters. It must not contain your name, username or email, and must not be a common word.</p>
            </div>
            <div class="card-footer bg-transparent text-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i> Change Password</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
