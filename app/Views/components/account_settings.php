<?php
/**
 * Account card and password change, shared by the staff Account Settings and
 * the reservist's Settings. Username and email show as dots until "Show" is
 * pressed, the same way the password fields do (handled in assets/js/app.js).
 *
 * @var CodeIgniter\Shield\Entities\User $user
 * @var list<string>                     $roles
 * @var string                           $action  password form target
 */
$errors  = session()->getFlashdata('errors') ?? [];
$invalid = static fn (string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
$masked  = static function (string $id, ?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return '<span class="masked" id="' . esc($id, 'attr') . '">'
        . '<span class="masked-dots" aria-label="hidden">••••••••••</span>'
        . '<span class="masked-real d-none">' . esc($value) . '</span>'
        . '</span>'
        . '<button type="button" class="btn btn-link btn-sm mask-toggle" data-mask-target="' . esc($id, 'attr') . '" aria-pressed="false">'
        . '<i class="bi bi-eye"></i> <span>Show</span></button>';
};
$passwordField = static function (string $name, string $label, string $autocomplete) use ($invalid): string {
    return '<label class="form-label" for="' . $name . '">' . esc($label) . '</label>'
        . '<div class="input-group">'
        . '<input type="password" class="form-control' . $invalid($name) . '" id="' . $name . '" name="' . $name . '" autocomplete="' . $autocomplete . '" required>'
        . '<button type="button" class="btn btn-outline-secondary password-toggle" data-password-target="' . $name . '" aria-pressed="false" title="Show password">'
        . '<i class="bi bi-eye"></i></button>'
        . '</div>';
};
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card portal-card h-100">
            <div class="card-header"><i class="bi bi-person-badge me-1"></i> Account</div>
            <div class="card-body">
                <dl class="info-list account-list mb-0">
                    <dt>Username</dt><dd><?= $masked('maskUsername', $user->username ?? null) ?></dd>
                    <dt>Email</dt><dd><?= $masked('maskEmail', $user->email ?? null) ?></dd>
                    <dt>Password</dt><dd><span class="masked-dots">••••••••••</span></dd>
                    <dt>Role</dt><dd><?= esc(implode(', ', $roles)) ?></dd>
                </dl>
                <p class="small text-muted mt-3 mb-0">Your username and email stay hidden on screen until you press Show.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <form method="post" action="<?= esc($action) ?>" class="card portal-card">
            <?= csrf_field() ?>
            <div class="card-header"><i class="bi bi-key me-1"></i> Change Password</div>
            <div class="card-body">
                <div class="mb-3">
                    <?= $passwordField('current_password', 'Current Password', 'current-password') ?>
                    <?= view('components/form_errors', ['field' => 'current_password']) ?>
                </div>
                <div class="mb-3">
                    <?= $passwordField('password', 'New Password', 'new-password') ?>
                    <?= view('components/form_errors', ['field' => 'password']) ?>
                </div>
                <div class="mb-2">
                    <?= $passwordField('password_confirm', 'Repeat New Password', 'new-password') ?>
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
