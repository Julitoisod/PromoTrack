<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\Shield\Controllers\RegisterController as ShieldRegisterController;

/**
 * Shield's registration form only collects email/username/password, and its
 * RegisterController only forwards POST fields that have a validation rule
 * (`$allowedPostFields = array_keys($rules)`). PromoTrack additionally needs
 * the profile fields on `users` — without an organizational unit the account
 * is inert, because UnitAccessFilter scopes every record to the user's unit.
 *
 * Adding the rules here rather than in Config\Validation::$registration keeps
 * Shield's own username/email/password rules as the single source of truth.
 */
class RegisterController extends ShieldRegisterController
{
    protected function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'full_name' => [
                'label' => 'Full Name',
                'rules' => ['required', 'max_length[150]'],
            ],
            'afp_serial_number' => [
                'label' => 'AFP Serial Number',
                'rules' => ['required', 'max_length[30]', 'is_unique[users.afp_serial_number]'],
            ],
            'organizational_unit_id' => [
                'label' => 'Unit',
                'rules' => ['required', 'is_natural_no_zero', 'is_not_unique[organizational_units.id]'],
            ],
        ]);
    }
}
