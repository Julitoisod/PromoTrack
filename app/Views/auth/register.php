<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Sign up<?= $this->endSection() ?>

<?= $this->section('main') ?>

<?php // ponytail: the unit list is this form's own option list, and Shield's
      // RegisterController passes no view data — a controller override would
      // only re-copy its three guard blocks to carry one query.
      $units = model(App\Models\UnitModel::class)->activeUnits(); ?>

<div class="auth-card">
    <div class="row g-0">

        <?= view('auth/_panel', [
            'lede' => 'Register with your AFP serial number and assigned unit. '
                . 'Your unit determines which reservist records you are able to see and work on.',
        ]) ?>

        <div class="col-12 col-md-7 auth-form">
            <h1>Sign up</h1>
            <p class="auth-lede">Create your PromoTrack account.</p>

            <?= $this->include('auth/_alerts') ?>

            <form action="<?= url_to('register') ?>" method="post">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="fullNameInput" name="full_name" autocomplete="name"
                           placeholder="Full Name" aria-label="Full Name" value="<?= old('full_name') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-hash" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="serialInput" name="afp_serial_number" maxlength="30"
                           placeholder="AFP Serial Number" aria-label="AFP Serial Number"
                           value="<?= old('afp_serial_number') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-building" aria-hidden="true"></i>
                    <select class="form-select" id="unitInput" name="organizational_unit_id" aria-label="Unit" required>
                        <option value="">Select your unit</option>
                        <?php foreach ($units as $unit) : ?>
                            <option value="<?= esc($unit['id']) ?>" <?= old('organizational_unit_id') == $unit['id'] ? 'selected' : '' ?>>
                                <?= esc($unit['unit_name']) ?> (<?= esc($unit['unit_code']) ?>)
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="auth-field">
                    <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                    <input type="email" class="form-control" id="emailInput" name="email" inputmode="email"
                           autocomplete="email" placeholder="Email Address" aria-label="Email Address"
                           value="<?= old('email') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="usernameInput" name="username" autocomplete="username"
                           placeholder="Username" aria-label="Username" value="<?= old('username') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <input type="password" class="form-control" id="passwordInput" name="password"
                           autocomplete="new-password" placeholder="Password" aria-label="Password" required>
                    <button type="button" class="auth-reveal" data-reveal="passwordInput" aria-controls="passwordInput">SHOW</button>
                </div>

                <div class="auth-field">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <input type="password" class="form-control" id="passwordConfirmInput" name="password_confirm"
                           autocomplete="new-password" placeholder="Repeat Password" aria-label="Repeat Password" required>
                    <button type="button" class="auth-reveal" data-reveal="passwordConfirmInput" aria-controls="passwordConfirmInput">SHOW</button>
                </div>

                <button type="submit" class="auth-submit mt-3">Sign up</button>

                <p class="auth-foot"><?= lang('Auth.haveAccount') ?> <a href="<?= url_to('login') ?>">Sign in</a></p>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<?= $this->include('auth/_reveal') ?>
<?= $this->endSection() ?>
