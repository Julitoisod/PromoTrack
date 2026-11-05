<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Models\ReservistModel;
use CodeIgniter\Shield\Controllers\RegisterController as ShieldRegisterController;

/**
 * Shield's registration form only collects email/username/password, and its
 * RegisterController only forwards POST fields that have a validation rule
 * (`$allowedPostFields = array_keys($rules)`). PromoTrack additionally needs
 * the profile fields on `users` -- without an organizational unit the account
 * is inert, because UnitAccessFilter scopes every record to the user's unit.
 *
 * Adding the rules here rather than in Config\Validation::$registration keeps
 * Shield's own username/email/password rules as the single source of truth.
 *
 * Sign-up is a reservist's own enrolment (see AuthGroups::$defaultGroup), so
 * the rules below also cover the reservist-record fields: their name, their
 * company, and whether they are still studying. Those are not `users`
 * columns -- UserModel's $allowedFields drops them from the account insert,
 * and the `register` event listener in Config\Events writes them to the
 * `reservists` row via ReservistRegistrationService.
 */
class RegisterController extends ShieldRegisterController
{
    protected function getValidationRules(): array
    {
        $yearLevels = implode(',', ReservistModel::STUDENT_YEAR_LEVELS);

        return array_merge(parent::getValidationRules(), [
            'first_name' => [
                'label' => 'First Name',
                'rules' => ['required', 'max_length[100]'],
            ],
            'middle_name' => [
                'label' => 'Middle Name',
                'rules' => ['permit_empty', 'max_length[100]'],
            ],
            'last_name' => [
                'label' => 'Last Name',
                'rules' => ['required', 'max_length[100]'],
            ],
            'suffix' => [
                'label' => 'Suffix',
                'rules' => ['permit_empty', 'max_length[10]'],
            ],
            'afp_serial_number' => [
                'label' => 'AFP Serial Number',
                'rules' => ['required', 'valid_afp_serial_number', 'max_length[30]', 'is_unique[users.afp_serial_number]'],
            ],
            'organizational_unit_id' => [
                'label' => 'Unit',
                'rules' => ['required', 'is_natural_no_zero', 'is_not_unique[organizational_units.id]'],
            ],
            'company_unit_id' => [
                'label' => 'Company',
                'rules' => ['required', 'is_natural_no_zero', 'is_not_unique[organizational_units.id]'],
            ],
            // Rank and an anchor date are what make a promotion standing
            // computable at all. Without them EligibilityService has no
            // rule to resolve and no time in grade to measure, and the
            // reservist sits at "Pending" from the moment they sign up.
            'current_rank_id' => [
                'label' => 'Current Rank',
                'rules' => ['required', 'is_natural_no_zero', 'is_not_unique[ranks.id]'],
            ],
            'date_of_enlistment' => [
                'label' => 'Date of Enlistment',
                'rules' => ['required', 'valid_date', 'not_future_date'],
            ],
            // Blank means never promoted since enlistment, which is a real
            // answer: time in grade then runs from the enlistment date.
            'date_of_last_promotion' => [
                'label' => 'Date of Last Promotion',
                'rules' => ['permit_empty', 'valid_date', 'not_future_date'],
            ],
            'is_student' => [
                'label' => 'Schooling',
                'rules' => ['required', 'in_list[0,1]'],
            ],
            // Only meaningful when is_student is 1; the service ignores it
            // otherwise, so an abandoned year level cannot be stored
            // against someone who answered "no longer studying".
            'student_year_level' => [
                'label' => 'Year Level',
                'rules' => ['permit_empty', 'in_list[' . $yearLevels . ']'],
            ],
        ]);
    }
}
