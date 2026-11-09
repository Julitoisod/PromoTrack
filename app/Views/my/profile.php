<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
/**
 * @var App\Entities\Reservist $reservist
 * @var array<string, string>  $attainments
 * @var list<string>           $yearLevels
 * @var list<array>            $companies
 */
$field = static fn (string $name, mixed $default = null) => old($name, $default);
$attainment = (string) $field('educational_attainment', $reservist->educational_attainment ?? '');
$errors     = session()->getFlashdata('errors') ?? [];
$invalid    = static fn (string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Keep your contact details and schooling current.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card portal-card mb-3">
            <div class="card-body text-center">
                <div class="profile-photo mx-auto mb-3">
                    <?php if ($photoUrl !== null): ?>
                        <img src="<?= esc($photoUrl) ?>" alt="Your photo">
                    <?php else: ?>
                        <i class="bi bi-person-fill" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <h2 class="h5 mb-0"><?= esc($reservist->getFullName()) ?></h2>
                <p class="text-muted small mb-3"><?= esc($reservist->reservist_number) ?></p>

                <form method="post" action="<?= esc(site_url('my/profile/photo')) ?>" enctype="multipart/form-data" class="text-start">
                    <?= csrf_field() ?>
                    <label class="form-label small fw-semibold" for="photoInput">Profile photo (JPG or PNG, up to 10 MB)</label>
                    <input type="file" class="form-control form-control-sm mb-2" id="photoInput" name="photo" accept=".jpg,.jpeg,.png" required>
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-upload me-1"></i> Upload photo</button>
                </form>
            </div>
        </div>

        <div class="card portal-card">
            <div class="card-header">Service Record</div>
            <div class="card-body">
                <dl class="info-list mb-2">
                    <dt>Serial Number</dt><dd><?= esc($reservist->afp_serial_number ?? '—') ?></dd>
                    <dt>CDC</dt><dd><?= esc($unit['unit_name'] ?? '—') ?></dd>
                    <dt>Rank</dt><dd><?= esc($rank['rank_name'] ?? '—') ?></dd>
                    <dt>Enlisted</dt><dd><?= $reservist->date_of_enlistment !== null ? esc($reservist->date_of_enlistment->toDateString()) : '—' ?></dd>
                    <dt>Last Promoted</dt><dd><?= $reservist->date_of_last_promotion !== null ? esc($reservist->date_of_last_promotion->toDateString()) : '—' ?></dd>
                </dl>
                <p class="small text-muted mb-0">These come from your service record. Ask your unit S1 clerk to correct them.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="post" action="<?= esc(site_url('my/profile')) ?>" class="card portal-card">
            <?= csrf_field() ?>
            <div class="card-header">Personal Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="contact_number">Contact Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control<?= $invalid('contact_number') ?>" id="contact_number" name="contact_number"
                               placeholder="09XXXXXXXXX" maxlength="13" value="<?= esc($field('contact_number', $reservist->contact_number)) ?>" required>
                        <?= view('components/form_errors', ['field' => 'contact_number']) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="date_of_birth">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control<?= $invalid('date_of_birth') ?>" id="date_of_birth" name="date_of_birth"
                               max="<?= date('Y-m-d') ?>" value="<?= esc($field('date_of_birth', $reservist->date_of_birth?->toDateString())) ?>" required>
                        <?= view('components/form_errors', ['field' => 'date_of_birth']) ?>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="address">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= $invalid('address') ?>" id="address" name="address" maxlength="255"
                               value="<?= esc($field('address', $reservist->address)) ?>" required>
                        <?= view('components/form_errors', ['field' => 'address']) ?>
                    </div>
                    <div class="col-md-4">
                        <span class="form-label d-block">Sex <span class="text-danger">*</span></span>
                        <?php foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label): ?>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="radio" name="sex" id="sex_<?= $value ?>" value="<?= $value ?>"
                                       <?= $field('sex', $reservist->sex) === $value ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="sex_<?= $value ?>"><?= $label ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?= view('components/form_errors', ['field' => 'sex']) ?>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="company_unit_id">Company <span class="text-danger">*</span></label>
                        <select class="form-select<?= $invalid('company_unit_id') ?>" id="company_unit_id" name="company_unit_id" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $companyOption): ?>
                                <option value="<?= esc($companyOption['id']) ?>" <?= (string) $field('company_unit_id', $reservist->company_unit_id) === (string) $companyOption['id'] ? 'selected' : '' ?>>
                                    <?= esc($companyOption['unit_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Companies of <?= esc($unit['unit_name'] ?? 'your CDC') ?>.</div>
                        <?= view('components/form_errors', ['field' => 'company_unit_id']) ?>
                    </div>
                </div>
            </div>

            <div class="card-header border-top">Schooling</div>
            <div class="card-body">
                <div class="row g-3" id="profileSchooling">
                    <div class="col-md-6">
                        <label class="form-label" for="educational_attainment">Educational Attainment <span class="text-danger">*</span></label>
                        <select class="form-select<?= $invalid('educational_attainment') ?>" id="educational_attainment" name="educational_attainment" required>
                            <option value="">Select</option>
                            <?php foreach ($attainments as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $attainment === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= view('components/form_errors', ['field' => 'educational_attainment']) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="degree_details">Degree / Specify</label>
                        <input type="text" class="form-control<?= $invalid('degree_details') ?>" id="degree_details" name="degree_details" maxlength="150"
                               placeholder="e.g. BS Information Technology" value="<?= esc($field('degree_details', $reservist->degree_details)) ?>">
                        <div class="form-text">Required for a Bachelor's Degree or Others.</div>
                        <?= view('components/form_errors', ['field' => 'degree_details']) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="is_student">Schooling Status <span class="text-danger">*</span></label>
                        <select class="form-select<?= $invalid('is_student') ?>" id="is_student" name="is_student" required>
                            <option value="1" <?= (string) $field('is_student', $reservist->is_student ? '1' : '0') === '1' ? 'selected' : '' ?>>Currently enrolled (student)</option>
                            <option value="0" <?= (string) $field('is_student', $reservist->is_student ? '1' : '0') === '0' ? 'selected' : '' ?>>Not currently enrolled</option>
                        </select>
                        <?= view('components/form_errors', ['field' => 'is_student']) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="student_year_level">Year Level</label>
                        <select class="form-select<?= $invalid('student_year_level') ?>" id="student_year_level" name="student_year_level">
                            <option value="">Not applicable</option>
                            <?php foreach ($yearLevels as $yearLevel): ?>
                                <option value="<?= esc($yearLevel) ?>" <?= $field('student_year_level', $reservist->student_year_level) === $yearLevel ? 'selected' : '' ?>><?= esc($yearLevel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Required for an undergraduate. It sets your E-level on ROTC Progress.</div>
                        <?= view('components/form_errors', ['field' => 'student_year_level']) ?>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Profile</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
