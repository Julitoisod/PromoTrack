<?php
/**
 * Shared reservist create/edit form fields.
 *
 * Expected variables:
 *   $reservist        Reservist entity when editing, or null when creating.
 *   $units            array of organizational_units rows (companies excluded).
 *   $companies        array of organizational_units rows of unit_type 'company'.
 *   $ranks            array of ranks rows.
 *   $educationTracks  array of education_tracks rows.
 *   $restrictedUnitId int|null - forces/locks the unit dropdown for unit-scoped users.
 */
$reservist ??= null;

$field = static function (string $name, $default = '') use ($reservist) {
    if ($reservist !== null && old($name) === null) {
        return $reservist->{$name} ?? $default;
    }

    return old($name, $default);
};
?>

<div class="row g-3">
    <div class="col-md-4">
        <label for="reservist_number" class="form-label">Reservist Number *</label>
        <input type="text" class="form-control" id="reservist_number" name="reservist_number"
               value="<?= esc($field('reservist_number')) ?>" required maxlength="30">
        <?= view('components/form_errors', ['field' => 'reservist_number']) ?>
    </div>
    <div class="col-md-4">
        <label for="afp_serial_number" class="form-label">AFP Serial Number</label>
        <input type="text" class="form-control" id="afp_serial_number" name="afp_serial_number"
               value="<?= esc($field('afp_serial_number')) ?>" maxlength="30">
        <?= view('components/form_errors', ['field' => 'afp_serial_number']) ?>
    </div>
    <div class="col-md-4">
        <label for="status" class="form-label">Status</label>
        <select class="form-select" id="status" name="status">
            <?php foreach (['active', 'inactive', 'discharged', 'deceased'] as $statusOption): ?>
                <option value="<?= esc($statusOption) ?>" <?= $field('status', 'active') === $statusOption ? 'selected' : '' ?>>
                    <?= esc(ucfirst($statusOption)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= view('components/form_errors', ['field' => 'status']) ?>
    </div>

    <div class="col-md-4">
        <label for="first_name" class="form-label">First Name *</label>
        <input type="text" class="form-control" id="first_name" name="first_name"
               value="<?= esc($field('first_name')) ?>" required maxlength="100">
        <?= view('components/form_errors', ['field' => 'first_name']) ?>
    </div>
    <div class="col-md-4">
        <label for="middle_name" class="form-label">Middle Name</label>
        <input type="text" class="form-control" id="middle_name" name="middle_name"
               value="<?= esc($field('middle_name')) ?>" maxlength="100">
        <?= view('components/form_errors', ['field' => 'middle_name']) ?>
    </div>
    <div class="col-md-3">
        <label for="last_name" class="form-label">Last Name *</label>
        <input type="text" class="form-control" id="last_name" name="last_name"
               value="<?= esc($field('last_name')) ?>" required maxlength="100">
        <?= view('components/form_errors', ['field' => 'last_name']) ?>
    </div>
    <div class="col-md-1">
        <label for="suffix" class="form-label">Suffix</label>
        <input type="text" class="form-control" id="suffix" name="suffix"
               value="<?= esc($field('suffix')) ?>" maxlength="10">
        <?= view('components/form_errors', ['field' => 'suffix']) ?>
    </div>

    <div class="col-md-4">
        <label for="sex" class="form-label">Sex</label>
        <select class="form-select" id="sex" name="sex">
            <option value="">-- Select --</option>
            <?php foreach (['male', 'female'] as $sexOption): ?>
                <option value="<?= esc($sexOption) ?>" <?= $field('sex') === $sexOption ? 'selected' : '' ?>>
                    <?= esc(ucfirst($sexOption)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= view('components/form_errors', ['field' => 'sex']) ?>
    </div>
    <div class="col-md-4">
        <label for="date_of_birth" class="form-label">Date of Birth</label>
        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
               value="<?= esc($field('date_of_birth')) ?>">
        <?= view('components/form_errors', ['field' => 'date_of_birth']) ?>
    </div>
    <div class="col-md-4">
        <label for="date_of_enlistment" class="form-label">Date of Enlistment</label>
        <input type="date" class="form-control" id="date_of_enlistment" name="date_of_enlistment"
               value="<?= esc($field('date_of_enlistment')) ?>">
        <?= view('components/form_errors', ['field' => 'date_of_enlistment']) ?>
    </div>

    <div class="col-md-4">
        <label for="current_rank_id" class="form-label">Current Rank</label>
        <select class="form-select" id="current_rank_id" name="current_rank_id">
            <option value="">-- Select --</option>
            <?php foreach ($ranks as $rank): ?>
                <option value="<?= esc((string) $rank['id']) ?>" <?= (string) $field('current_rank_id') === (string) $rank['id'] ? 'selected' : '' ?>>
                    <?= esc($rank['rank_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= view('components/form_errors', ['field' => 'current_rank_id']) ?>
    </div>
    <div class="col-md-4">
        <label for="date_of_last_promotion" class="form-label">Date of Last Promotion</label>
        <input type="date" class="form-control" id="date_of_last_promotion" name="date_of_last_promotion"
               value="<?= esc($field('date_of_last_promotion')) ?>">
        <?= view('components/form_errors', ['field' => 'date_of_last_promotion']) ?>
    </div>
    <div class="col-md-4">
        <label for="education_track_id" class="form-label">Education Track</label>
        <select class="form-select" id="education_track_id" name="education_track_id">
            <option value="">-- Select --</option>
            <?php foreach ($educationTracks as $track): ?>
                <option value="<?= esc((string) $track['id']) ?>" <?= (string) $field('education_track_id') === (string) $track['id'] ? 'selected' : '' ?>>
                    <?= esc($track['track_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= view('components/form_errors', ['field' => 'education_track_id']) ?>
    </div>

    <div class="col-md-4">
        <label for="is_student" class="form-label">Still a Student?</label>
        <select class="form-select" id="is_student" name="is_student">
            <option value="0" <?= (string) $field('is_student', '0') !== '1' ? 'selected' : '' ?>>No — no longer a student</option>
            <option value="1" <?= (string) $field('is_student', '0') === '1' ? 'selected' : '' ?>>Yes — still studying</option>
        </select>
        <?= view('components/form_errors', ['field' => 'is_student']) ?>
    </div>
    <div class="col-md-4">
        <label for="student_year_level" class="form-label">Year Level</label>
        <select class="form-select" id="student_year_level" name="student_year_level">
            <option value="">-- Not applicable --</option>
            <?php
            // A record saved before the semester list keeps its whole-year
            // value selectable, so saving the form does not silently drop it.
            $yearLevelOptions = App\Models\ReservistModel::STUDENT_YEAR_LEVELS;
            $currentYearLevel = (string) $field('student_year_level');
            if (in_array($currentYearLevel, App\Models\ReservistModel::LEGACY_YEAR_LEVELS, true)) {
                $yearLevelOptions[] = $currentYearLevel;
            }
            ?>
            <?php foreach ($yearLevelOptions as $yearLevel): ?>
                <option value="<?= esc($yearLevel) ?>" <?= $currentYearLevel === $yearLevel ? 'selected' : '' ?>>
                    <?= esc($yearLevel) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Ignored unless the reservist is still studying.</div>
        <?= view('components/form_errors', ['field' => 'student_year_level']) ?>
    </div>

    <div class="col-md-4">
        <label for="educational_attainment" class="form-label">Educational Attainment</label>
        <select class="form-select" id="educational_attainment" name="educational_attainment">
            <option value="">-- Select --</option>
            <?php foreach (App\Models\ReservistModel::EDUCATIONAL_ATTAINMENTS as $attainValue => $attainLabel): ?>
                <option value="<?= esc($attainValue) ?>" <?= (string) $field('educational_attainment') === $attainValue ? 'selected' : '' ?>>
                    <?= esc($attainLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Undergraduate + still studying puts the reservist on the ROTC ladder.</div>
        <?= view('components/form_errors', ['field' => 'educational_attainment']) ?>
    </div>
    <div class="col-md-4">
        <label for="degree_details" class="form-label">Degree / Specify</label>
        <input type="text" class="form-control" id="degree_details" name="degree_details" maxlength="150"
               value="<?= esc($field('degree_details')) ?>">
        <?= view('components/form_errors', ['field' => 'degree_details']) ?>
    </div>

    <div class="col-md-4">
        <label for="company_unit_id" class="form-label">Company</label>
        <select class="form-select" id="company_unit_id" name="company_unit_id">
            <option value="">-- Select --</option>
            <?php foreach ($companies as $company): ?>
                <option value="<?= esc((string) $company['id']) ?>" <?= (string) $field('company_unit_id') === (string) $company['id'] ? 'selected' : '' ?>>
                    <?= esc($company['unit_name']) ?><?= $company['parent_unit_name'] !== null ? ' — ' . esc($company['parent_unit_name']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Maintained in Reference Data as units of type <code>company</code>.</div>
        <?= view('components/form_errors', ['field' => 'company_unit_id']) ?>
    </div>

    <div class="col-md-4">
        <label for="organizational_unit_id" class="form-label">Organizational Unit</label>
        <?php if ($restrictedUnitId !== null): ?>
            <input type="hidden" name="organizational_unit_id" value="<?= esc((string) $restrictedUnitId) ?>">
            <input type="text" class="form-control" disabled
                   value="<?= esc(array_values(array_filter($units, static fn ($unit) => (int) $unit['id'] === $restrictedUnitId))[0]['unit_name'] ?? 'Your Unit') ?>">
        <?php else: ?>
            <select class="form-select" id="organizational_unit_id" name="organizational_unit_id">
                <option value="">-- Select --</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= esc((string) $unit['id']) ?>" <?= (string) $field('organizational_unit_id') === (string) $unit['id'] ? 'selected' : '' ?>>
                        <?= esc($unit['unit_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?= view('components/form_errors', ['field' => 'organizational_unit_id']) ?>
    </div>
    <div class="col-md-4">
        <label for="contact_number" class="form-label">Contact Number</label>
        <input type="text" class="form-control" id="contact_number" name="contact_number"
               value="<?= esc($field('contact_number')) ?>" maxlength="20">
        <?= view('components/form_errors', ['field' => 'contact_number']) ?>
    </div>
    <div class="col-md-4">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= esc($field('email')) ?>" maxlength="150">
        <?= view('components/form_errors', ['field' => 'email']) ?>
    </div>

    <div class="col-12">
        <label for="address" class="form-label">Address</label>
        <textarea class="form-control" id="address" name="address" rows="2"><?= esc($field('address')) ?></textarea>
        <?= view('components/form_errors', ['field' => 'address']) ?>
    </div>
</div>
