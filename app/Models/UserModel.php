<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/**
 * Extends CodeIgniter Shield's UserModel with the PromoTrack-specific
 * profile fields added by the `AddProfileFieldsToUsersMigration`
 * migration, so they can be mass-assigned through `save()`/`insert()`/
 * `update()` calls (for example, from `InitialSeeder` and the future
 * `UsersController`).
 */
class UserModel extends ShieldUserModel
{
    /**
     * @var list<string>
     */
    protected $allowedFields = [
        'username',
        'status',
        'status_message',
        'active',
        'last_active',
        'full_name',
        'afp_serial_number',
        'organizational_unit_id',
        'deactivated_at',
        'deactivation_reason',
    ];
}
