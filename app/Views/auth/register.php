<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Sign up<?= $this->endSection() ?>
<?= $this->section('heroIcon') ?>bi-person-plus-fill<?= $this->endSection() ?>
<?= $this->section('heroTitle') ?>Create Account<?= $this->endSection() ?>
<?= $this->section('heroSubtitle') ?>Register as an AFP Reservist<?= $this->endSection() ?>

<?= $this->section('main') ?>

<?php // The option lists are this form's own, and Shield's RegisterController
      // passes no view data -- a controller override would only re-copy its
      // guard blocks to carry a query.
      $unitModel   = model(App\Models\UnitModel::class);
      $rankModel   = model(App\Models\RankModel::class);
      $cdcs        = $unitModel->activeCdcs();
      $companies   = $unitModel->activeCompanies();
      $rankGroups  = $rankModel->ranksBySignupCategory();
      $categories  = App\Models\RankModel::SIGNUP_CATEGORIES;
      $attainments = App\Models\ReservistModel::EDUCATIONAL_ATTAINMENTS;
      $yearLevels  = App\Models\ReservistModel::STUDENT_YEAR_LEVELS;
      $cdcIds      = array_map('intval', array_column($cdcs, 'id'));
      $oldAttain   = (string) old('educational_attainment', 'undergraduate');
      $errors      = (array) (session('errors') ?? []);
      $invalid     = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
      $feedback    = static fn (string $field): string => isset($errors[$field])
          ? '<div class="invalid-feedback d-block">' . esc($errors[$field]) . '</div>' : ''; ?>

<form action="<?= url_to('register') ?>" method="post" id="registerForm" class="register-grid">
    <?= csrf_field() ?>

    <div class="register-main">

        <?php if (session('error') !== null) : ?>
            <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
        <?php elseif ($errors !== []) : ?>
            <div class="alert alert-danger" role="alert">
                <strong>Please check the form.</strong> <?= esc((string) count($errors)) ?> field(s) need your attention.
            </div>
        <?php endif ?>

        <section class="reg-card">
            <h2><i class="bi bi-person-fill" aria-hidden="true"></i> Personal Information</h2>

            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="firstNameInput">First Name <span class="req">*</span></label>
                    <input type="text" class="form-control<?= $invalid('first_name') ?>" id="firstNameInput" name="first_name"
                           autocomplete="given-name" maxlength="100" placeholder="First name"
                           value="<?= esc(old('first_name')) ?>" required data-summary="name">
                    <?= $feedback('first_name') ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="middleNameInput">Middle Name <span class="req">*</span></label>
                    <input type="text" class="form-control<?= $invalid('middle_name') ?>" id="middleNameInput" name="middle_name"
                           autocomplete="additional-name" maxlength="100" placeholder="Middle name"
                           value="<?= esc(old('middle_name')) ?>" required data-summary="name">
                    <?= $feedback('middle_name') ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="lastNameInput">Last Name <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control<?= $invalid('last_name') ?>" id="lastNameInput" name="last_name"
                               autocomplete="family-name" maxlength="100" placeholder="Last name"
                               value="<?= esc(old('last_name')) ?>" required data-summary="name">
                        <select class="form-select suffix-select" id="suffixInput" name="suffix" aria-label="Suffix" data-summary="name">
                            <?php foreach (['' => 'Suffix', 'Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV'] as $value => $label) : ?>
                                <option value="<?= esc($value) ?>" <?= old('suffix') === $value && $value !== '' ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <?= $feedback('last_name') ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="serialInput">Serial Number <span class="req">*</span></label>
                    <input type="text" class="form-control<?= $invalid('afp_serial_number') ?>" id="serialInput" name="afp_serial_number"
                           maxlength="30" placeholder="Serial number"
                           value="<?= esc(old('afp_serial_number')) ?>" required>
                    <?= $feedback('afp_serial_number') ?>
                </div>

                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="unitInput">CDC <span class="req">*</span></label>
                    <select class="form-select<?= $invalid('organizational_unit_id') ?>" id="unitInput" name="organizational_unit_id" required data-summary="cdc">
                        <option value="">Select CDC</option>
                        <?php foreach ($cdcs as $cdc) : ?>
                            <option value="<?= esc($cdc['id']) ?>" <?= (string) old('organizational_unit_id') === (string) $cdc['id'] ? 'selected' : '' ?>>
                                <?= esc($cdc['unit_name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?= $feedback('organizational_unit_id') ?>
                </div>
                <div class="col-md-6 col-xl-5">
                    <label class="form-label" for="companyInput">Company <span class="req">*</span></label>
                    <select class="form-select<?= $invalid('company_unit_id') ?>" id="companyInput" name="company_unit_id" required data-summary="company">
                        <option value="">Select Company</option>
                        <?php foreach ($companies as $company) : ?>
                            <?php if (! in_array((int) $company['parent_unit_id'], $cdcIds, true)) {
                                continue;
                            } ?>
                            <option value="<?= esc($company['id']) ?>" data-parent="<?= esc($company['parent_unit_id']) ?>"
                                <?= (string) old('company_unit_id') === (string) $company['id'] ? 'selected' : '' ?>>
                                <?= esc($company['unit_name']) ?><?= $company['parent_unit_name'] !== null ? ' (' . esc($company['parent_unit_name']) . ')' : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?= $feedback('company_unit_id') ?>
                </div>
                <div class="col-md-12 col-xl-3">
                    <span class="form-label d-block">Sex <span class="req">*</span></span>
                    <div class="reg-radios">
                        <?php foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label) : ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sex" id="sex_<?= $value ?>" value="<?= $value ?>"
                                       <?= old('sex', 'male') === $value ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="sex_<?= $value ?>"><?= $label ?></label>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?= $feedback('sex') ?>
                </div>

                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="birthInput">Date of Birth <span class="req">*</span></label>
                    <input type="date" class="form-control<?= $invalid('date_of_birth') ?>" id="birthInput" name="date_of_birth"
                           value="<?= esc(old('date_of_birth')) ?>" max="<?= date('Y-m-d') ?>" required>
                    <?= $feedback('date_of_birth') ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="contactInput">Contact Number <span class="req">*</span></label>
                    <input type="tel" class="form-control<?= $invalid('contact_number') ?>" id="contactInput" name="contact_number"
                           inputmode="tel" maxlength="13" placeholder="09XXXXXXXXX" autocomplete="tel"
                           value="<?= esc(old('contact_number')) ?>" required>
                    <?= $feedback('contact_number') ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="emailInput">Email Address <span class="req">*</span></label>
                    <input type="email" class="form-control<?= $invalid('email') ?>" id="emailInput" name="email" inputmode="email"
                           autocomplete="email" placeholder="youremail@example.com"
                           value="<?= esc(old('email')) ?>" required>
                    <?= $feedback('email') ?>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="addressInput">Address <span class="req">*</span></label>
                    <input type="text" class="form-control<?= $invalid('address') ?>" id="addressInput" name="address"
                           maxlength="255" placeholder="Enter your address" autocomplete="street-address"
                           value="<?= esc(old('address')) ?>" required>
                    <?= $feedback('address') ?>
                </div>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-lg-7">
                <section class="reg-card h-100">
                    <h2><i class="bi bi-mortarboard-fill" aria-hidden="true"></i> Educational Attainment <span class="req">*</span></h2>

                    <div class="reg-radios reg-radios-wrap" role="radiogroup" aria-label="Educational Attainment">
                        <?php foreach ($attainments as $value => $label) : ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="educational_attainment" id="attain_<?= $value ?>"
                                       value="<?= $value ?>" data-label="<?= esc($label) ?>" <?= $oldAttain === $value ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="attain_<?= $value ?>"><?= esc($label) ?></label>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?= $feedback('educational_attainment') ?>

                    <div class="mt-3" data-show-for="undergraduate">
                        <label class="form-label" for="yearLevelInput">If Undergraduate: Year Level <span class="req">*</span></label>
                        <select class="form-select<?= $invalid('student_year_level') ?>" id="yearLevelInput" name="student_year_level">
                            <option value="">Select Year Level</option>
                            <?php foreach ($yearLevels as $yearLevel) : ?>
                                <option value="<?= esc($yearLevel) ?>" <?= old('student_year_level') === $yearLevel ? 'selected' : '' ?>><?= esc($yearLevel) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= $feedback('student_year_level') ?>
                    </div>

                    <div class="mt-3" data-show-for="bachelor masteral doctorate others">
                        <label class="form-label" for="degreeInput">
                            <span data-degree-label>Degree</span> <span class="req" data-degree-required>*</span>
                        </label>
                        <input type="text" class="form-control<?= $invalid('degree_details') ?>" id="degreeInput" name="degree_details"
                               maxlength="150" placeholder="e.g. BS Information Technology" value="<?= esc(old('degree_details')) ?>">
                        <?= $feedback('degree_details') ?>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="reg-card h-100">
                    <h2><i class="bi bi-chevron-double-up" aria-hidden="true"></i> Current Rank</h2>

                    <label class="form-label" for="rankCategoryInput">Rank Category <span class="req">*</span></label>
                    <select class="form-select<?= $invalid('rank_category') ?>" id="rankCategoryInput" name="rank_category" required>
                        <option value="">Select Rank Category</option>
                        <?php foreach ($categories as $key => $category) : ?>
                            <option value="<?= esc($key) ?>" <?= old('rank_category') === $key ? 'selected' : '' ?>><?= esc($category['label']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $feedback('rank_category') ?>

                    <label class="form-label mt-3" for="rankInput">Rank <span class="req">*</span></label>
                    <select class="form-select<?= $invalid('current_rank_id') ?>" id="rankInput" name="current_rank_id" required data-summary="rank">
                        <option value="">Select Rank</option>
                        <?php foreach ($rankGroups as $key => $ranks) : ?>
                            <optgroup label="<?= esc($categories[$key]['label']) ?>" data-category="<?= esc($key) ?>">
                                <?php foreach ($ranks as $rank) : ?>
                                    <option value="<?= esc($rank['id']) ?>" data-category="<?= esc($key) ?>"
                                            data-rcdg="<?= App\Models\RankModel::isRcdgLevel($rank) ? '1' : '0' ?>"
                                            <?= (string) old('current_rank_id') === (string) $rank['id'] ? 'selected' : '' ?>>
                                        <?= esc($rank['rank_name']) ?>
                                    </option>
                                <?php endforeach ?>
                            </optgroup>
                        <?php endforeach ?>
                    </select>
                    <?= $feedback('current_rank_id') ?>

                    <div class="reg-info mt-3">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <span>If you are an ROTC student, select your MS rank. For Officers, choose your officer rank.</span>
                    </div>
                    <div class="reg-warning mt-2 d-none" id="rcdgNotice" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span><strong>RCDG level.</strong> Promotion for a Master Sergeant is processed at the RCDG level
                            and cannot be applied for in this system.</span>
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="reg-card h-100">
                    <h2><i class="bi bi-calendar-event" aria-hidden="true"></i> Service Dates</h2>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="enlistmentInput">Date of Enlistment <span class="req">*</span></label>
                            <input type="date" class="form-control<?= $invalid('date_of_enlistment') ?>" id="enlistmentInput" name="date_of_enlistment"
                                   value="<?= esc(old('date_of_enlistment')) ?>" max="<?= date('Y-m-d') ?>" required data-summary="enlistment">
                            <?= $feedback('date_of_enlistment') ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="lastPromotionInput">Date of Last Promotion</label>
                            <input type="date" class="form-control<?= $invalid('date_of_last_promotion') ?>" id="lastPromotionInput" name="date_of_last_promotion"
                                   value="<?= esc(old('date_of_last_promotion')) ?>" max="<?= date('Y-m-d') ?>" data-summary="promotion">
                            <div class="form-text">Leave blank if never promoted.</div>
                            <?= $feedback('date_of_last_promotion') ?>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="reg-card h-100">
                    <h2><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Additional Information</h2>
                    <label class="form-label" for="studentInput">Schooling Status <span class="req">*</span></label>
                    <select class="form-select<?= $invalid('is_student') ?>" id="studentInput" name="is_student" required>
                        <option value="">Select Status</option>
                        <option value="1" <?= old('is_student') === '1' ? 'selected' : '' ?>>Currently enrolled (student)</option>
                        <option value="0" <?= old('is_student') === '0' ? 'selected' : '' ?>>Not currently enrolled</option>
                    </select>
                    <?= $feedback('is_student') ?>
                </section>
            </div>
        </div>

        <section class="reg-card">
            <h2><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Account</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="usernameInput">Username <span class="req">*</span></label>
                    <input type="text" class="form-control<?= $invalid('username') ?>" id="usernameInput" name="username"
                           autocomplete="username" placeholder="Choose a username" value="<?= esc(old('username')) ?>" required>
                    <?= $feedback('username') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="passwordInput">Password <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control<?= $invalid('password') ?>" id="passwordInput" name="password"
                               autocomplete="new-password" placeholder="Create a password" required>
                        <button type="button" class="btn btn-outline-secondary auth-reveal" data-reveal="passwordInput" aria-controls="passwordInput">SHOW</button>
                    </div>
                    <?= $feedback('password') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="passwordConfirmInput">Repeat Password <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control<?= $invalid('password_confirm') ?>" id="passwordConfirmInput" name="password_confirm"
                               autocomplete="new-password" placeholder="Repeat the password" required>
                        <button type="button" class="btn btn-outline-secondary auth-reveal" data-reveal="passwordConfirmInput" aria-controls="passwordConfirmInput">SHOW</button>
                    </div>
                    <?= $feedback('password_confirm') ?>
                </div>
            </div>
            <?php // Shield enforces CompositionValidator, NothingPersonalValidator and
                  // DictionaryValidator. Without this note a rejected password just
                  // produces "Passwords cannot contain re-hashed personal information",
                  // which reads as a system fault rather than as a rule to follow. ?>
            <p class="form-text mb-0 mt-2">
                At least 8 characters. It must not contain your name, username or email, and must not be a common word.
            </p>
        </section>
    </div>

    <aside class="register-side">
        <section class="reg-card reg-summary" aria-live="polite">
            <h2><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i> Registration Summary</h2>
            <dl>
                <dt>Name:</dt><dd id="sumName">-</dd>
                <dt>CDC:</dt><dd id="sumCdc">-</dd>
                <dt>Company:</dt><dd id="sumCompany">-</dd>
                <dt>Rank:</dt><dd id="sumRank">-</dd>
                <dt>Educational Track:</dt><dd id="sumTrack">-</dd>
                <dt>Date of Enlistment:</dt><dd id="sumEnlistment">-</dd>
                <dt>Date of Last Promotion:</dt><dd id="sumPromotion">-</dd>
            </dl>
        </section>

        <section class="reg-reminders">
            <h3><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> Important Reminders:</h3>
            <ol>
                <li>Fields marked with <span class="req">*</span> are required.</li>
                <li>Ensure all information is accurate before submitting.</li>
                <li>For Master Sergeant, promotion application is processed at RCDG level and is not available in the system.</li>
            </ol>
        </section>

        <button type="submit" class="auth-submit reg-submit">
            <i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i> Register Account
        </button>

        <p class="auth-foot"><?= lang('Auth.haveAccount') ?> <a href="<?= url_to('login') ?>">Sign in</a></p>
    </aside>
</form>

<div class="modal fade" id="rcdgModal" tabindex="-1" aria-labelledby="rcdgModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rcdgModalTitle"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> RCDG Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Promotion for a <strong>Master Sergeant</strong> is processed at the RCDG level. You can still
                register, but you cannot apply for that promotion using PromoTrack.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">I understand</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<?= $this->include('auth/_reveal') ?>
<script src="<?= esc(base_url('assets/js/register.js')) ?>"></script>
<?= $this->endSection() ?>
