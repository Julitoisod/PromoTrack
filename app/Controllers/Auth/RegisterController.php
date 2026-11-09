<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Models\RankModel;
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
 * the rules below also cover the reservist-record fields. Those are not
 * `users` columns -- UserModel's $allowedFields drops them from the account
 * insert, and the `register` event listener in Config\Events writes them to
 * the `reservists` row via ReservistRegistrationService.
 *
 * The field set follows the client's "Sign up.docx" (2026-11-09): first,
 * middle and last name and serial number are all required; CDC then company;
 * sex, birth date, contact number and address; educational attainment with a
 * year level for undergraduates and the degree written in for a bachelor's;
 * a rank category before the rank itself.
 */
class RegisterController extends ShieldRegisterController
{
    protected function getValidationRules(): array
    {
        $yearLevels  = implode(',', ReservistModel::STUDENT_YEAR_LEVELS);
        $attainments = implode(',', array_keys(ReservistModel::EDUCATIONAL_ATTAINMENTS));
        $categories  = implode(',', array_keys(RankModel::SIGNUP_CATEGORIES));
        $attainment  = (string) $this->request->getPost('educational_attainment');

        return array_merge(parent::getValidationRules(), [
            'first_name' => [
                'label' => 'First Name',
                'rules' => ['required', 'max_length[100]'],
            ],
            // Required by the client; only its initial is printed in a
            // generated name ("Dela Cruz, Juan P.").
            'middle_name' => [
                'label' => 'Middle Name',
                'rules' => ['required', 'max_length[100]'],
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
                'label' => 'Serial Number',
                'rules' => ['required', 'valid_afp_serial_number', 'max_length[30]', 'is_unique[users.afp_serial_number]'],
            ],
            // Labelled "CDC" on the form: the reservist's unit is their CDC.
            'organizational_unit_id' => [
                'label' => 'CDC',
                'rules' => ['required', 'is_natural_no_zero', 'is_not_unique[organizational_units.id]'],
            ],
            'company_unit_id' => [
                'label' => 'Company',
                'rules' => ['required', 'is_natural_no_zero', 'company_in_unit[organizational_unit_id]'],
            ],
            'sex' => [
                'label' => 'Sex',
                'rules' => ['required', 'in_list[male,female]'],
            ],
            'date_of_birth' => [
                'label' => 'Date of Birth',
                'rules' => ['required', 'valid_date', 'not_future_date'],
            ],
            'contact_number' => [
                'label' => 'Contact Number',
                'rules' => ['required', 'ph_mobile_number', 'max_length[30]'],
            ],
            'address' => [
                'label' => 'Address',
                'rules' => ['required', 'max_length[255]'],
            ],
            'educational_attainment' => [
                'label' => 'Educational Attainment',
                'rules' => ['required', 'in_list[' . $attainments . ']'],
            ],
            'degree_details' => [
                'label' => $attainment === 'others' ? 'Educational Attainment (specify)' : "Bachelor's Degree",
                'rules' => in_array($attainment, ReservistModel::ATTAINMENTS_NEEDING_DETAILS, true)
                    ? ['required', 'max_length[150]']
                    : ['permit_empty', 'max_length[150]'],
            ],
            'student_year_level' => [
                'label' => 'Year Level',
                'rules' => $attainment === 'undergraduate'
                    ? ['required', 'in_list[' . $yearLevels . ']']
                    : ['permit_empty', 'in_list[' . $yearLevels . ']'],
            ],
            'rank_category' => [
                'label' => 'Rank Category',
                'rules' => ['required', 'in_list[' . $categories . ']'],
            ],
            // Rank and an anchor date are what make a promotion standing
            // computable at all. Without them EligibilityService has no
            // rule to resolve and no time in grade to measure, and the
            // reservist sits at "Pending" from the moment they sign up.
            'current_rank_id' => [
                'label' => 'Current Rank',
                'rules' => ['required', 'is_natural_no_zero', 'rank_in_category[rank_category]'],
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
            // The MLES column is worked out from the educational attainment
            // (ReservistRegistrationService::trackFor); a posted track still
            // wins so a caller that knows better can say so.
            'education_track_id' => [
                'label' => 'Education Track',
                'rules' => ['permit_empty', 'is_natural_no_zero', 'is_not_unique[education_tracks.id]'],
            ],
            'is_student' => [
                'label' => 'Schooling Status',
                'rules' => ['required', 'in_list[0,1]'],
            ],
        ]);
    }
}
