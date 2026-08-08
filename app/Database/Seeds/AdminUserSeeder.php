<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

/**
 * Creates the initial superadmin account required to operate the
 * system before any other administrator exists.
 *
 * The seeded password is a placeholder and MUST be changed immediately
 * after the first login in any non-local environment.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = auth()->getProvider();

        $email = 'superadmin@promotrack.local';

        if ($users->findByCredentials(['email' => $email]) !== null) {
            return;
        }

        $headquarters = $this->db->table('organizational_units')
            ->where('unit_code', 'HHQ-SAMPLE')
            ->get()
            ->getFirstRow('array');

        $user = new User([
            'username'               => 'superadmin',
            'email'                  => $email,
            'password'               => 'ChangeMe!12345',
            'full_name'              => 'System Super Administrator',
            'afp_serial_number'      => null,
            'organizational_unit_id' => $headquarters['id'] ?? null,
        ]);

        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup('superadmin');
    }
}
