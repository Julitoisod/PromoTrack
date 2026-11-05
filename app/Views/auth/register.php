<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Sign up<?= $this->endSection() ?>

<?= $this->section('main') ?>

<?php // ponytail: the unit and company lists are this form's own option lists,
      // and Shield's RegisterController passes no view data — a controller
      // override would only re-copy its three guard blocks to carry a query.
      $unitModel  = model(App\Models\UnitModel::class);
      $units      = $unitModel->activeUnits();
      $companies  = $unitModel->activeCompanies();
      $ranks      = model(App\Models\RankModel::class)->activeRanksOrdered();
      $yearLevels = App\Models\ReservistModel::STUDENT_YEAR_LEVELS; ?>

<div class="auth-card">
    <div class="row g-0">

        <?= view('auth/_panel', [
            'lede' => 'Register with your AFP serial number, unit, and company. '
                . 'Your account is matched to your reservist record so you can upload '
                . 'your own promotion documents and follow your package as it moves.',
        ]) ?>

        <div class="col-12 col-md-7 auth-form">
            <h1>Sign up</h1>
            <p class="auth-lede">Create your PromoTrack reservist account.</p>

            <?= $this->include('auth/_alerts') ?>

            <form action="<?= url_to('register') ?>" method="post">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="firstNameInput" name="first_name"
                           autocomplete="given-name" maxlength="100"
                           placeholder="First Name" aria-label="First Name" value="<?= old('first_name') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="middleNameInput" name="middle_name"
                           autocomplete="additional-name" maxlength="100"
                           placeholder="Middle Name (optional)" aria-label="Middle Name" value="<?= old('middle_name') ?>">
                </div>

                <div class="auth-field">
                    <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="lastNameInput" name="last_name"
                           autocomplete="family-name" maxlength="100"
                           placeholder="Last Name" aria-label="Last Name" value="<?= old('last_name') ?>" required>
                </div>

                <div class="auth-field">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="suffixInput" name="suffix" maxlength="10"
                           placeholder="Suffix, e.g. Jr. (optional)" aria-label="Suffix" value="<?= old('suffix') ?>">
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
                    <i class="bi bi-flag-fill" aria-hidden="true"></i>
                    <select class="form-select" id="companyInput" name="company_unit_id" aria-label="Company" required>
                        <option value="">Select your company</option>
                        <?php foreach ($companies as $company) : ?>
                            <option value="<?= esc($company['id']) ?>" <?= old('company_unit_id') == $company['id'] ? 'selected' : '' ?>>
                                <?= esc($company['unit_name']) ?><?= $company['parent_unit_name'] !== null ? ' — ' . esc($company['parent_unit_name']) : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="auth-field">
                    <i class="bi bi-chevron-double-up" aria-hidden="true"></i>
                    <select class="form-select" id="rankInput" name="current_rank_id" aria-label="Current Rank" required>
                        <option value="">Select your current rank</option>
                        <?php foreach ($ranks as $rank) : ?>
                            <option value="<?= esc($rank['id']) ?>" <?= old('current_rank_id') == $rank['id'] ? 'selected' : '' ?>>
                                <?= esc($rank['rank_name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="auth-field auth-field-date">
                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                    <label for="enlistmentInput">Date of Enlistment</label>
                    <input type="date" class="form-control" id="enlistmentInput" name="date_of_enlistment"
                           value="<?= old('date_of_enlistment') ?>" required>
                </div>

                <div class="auth-field auth-field-date">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                    <label for="lastPromotionInput">Date of Last Promotion <span class="text-muted">(leave blank if never promoted)</span></label>
                    <input type="date" class="form-control" id="lastPromotionInput" name="date_of_last_promotion"
                           value="<?= old('date_of_last_promotion') ?>">
                </div>

                <div class="auth-field">
                    <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                    <select class="form-select" id="studentInput" name="is_student" aria-label="Are you still a student?" required>
                        <option value="">Are you still a student?</option>
                        <option value="1" <?= old('is_student') === '1' ? 'selected' : '' ?>>Yes — still studying</option>
                        <option value="0" <?= old('is_student') === '0' ? 'selected' : '' ?>>No — no longer a student</option>
                    </select>
                </div>

                <?php // No JS to show/hide this: "Not applicable" is a valid
                      // answer, and ReservistRegistrationService drops any year
                      // level submitted alongside "no longer a student". ?>
                <div class="auth-field">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <select class="form-select" id="yearLevelInput" name="student_year_level" aria-label="Year Level">
                        <option value="">Year level — not applicable</option>
                        <?php foreach ($yearLevels as $yearLevel) : ?>
                            <option value="<?= esc($yearLevel) ?>" <?= old('student_year_level') === $yearLevel ? 'selected' : '' ?>>
                                <?= esc($yearLevel) ?>
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

                <?php // Shield enforces CompositionValidator, NothingPersonalValidator and
                      // DictionaryValidator. Without this note a rejected password just
                      // produces "Passwords cannot contain re-hashed personal information",
                      // which reads as a system fault rather than as a rule to follow. ?>
                <p class="auth-hint">
                    Your password must be at least 8 characters, must not contain your name,
                    username or email, and must not be a common word.
                </p>

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
