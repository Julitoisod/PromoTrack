<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

/**
 * Creates one demo account per role (besides superadmin, already
 * created by AdminUserSeeder) so the full CDC -> RCDG -> external
 * workflow can be exercised end-to-end without manual account setup.
 *
 * Seeded passwords are placeholders and MUST be changed immediately
 * after first login in any non-local environment.
 */
class DemoUsersSeeder extends Seeder
{
    /**
     * @var list<array{username: string, email: string, full_name: string, group: string, unit_code: string}>
     */
    private const DEMO_USERS = [
        ['username' => 'clerk.demo', 'email' => 'clerk.demo@promotrack.local', 'full_name' => 'Demo Unit S1 Clerk', 'group' => 'unit_s1_clerk', 'unit_code' => 'UNIT-A-SAMPLE'],
        ['username' => 'cdc.demo', 'email' => 'cdc.demo@promotrack.local', 'full_name' => 'Demo CDC Administrator', 'group' => 'cdc_administrator', 'unit_code' => 'CDC-SAMPLE'],
        ['username' => 'rcdg.demo', 'email' => 'rcdg.demo@promotrack.local', 'full_name' => 'Demo RCDG Reviewer', 'group' => 'rcdg_reviewer', 'unit_code' => 'RCDG-SAMPLE'],
        ['username' => 'commander.demo', 'email' => 'commander.demo@promotrack.local', 'full_name' => 'Demo Commander/Viewer', 'group' => 'commander_viewer', 'unit_code' => 'HHQ-SAMPLE'],
        ['username' => 'records.demo', 'email' => 'records.demo@promotrack.local', 'full_name' => 'Demo Records Officer', 'group' => 'records_officer', 'unit_code' => 'HHQ-SAMPLE'],
    ];

    public function run(): void
    {
        $users = auth()->getProvider();

        foreach (self::DEMO_USERS as $demo) {
            if ($users->findByCredentials(['email' => $demo['email']]) !== null) {
                continue;
            }

            $unit = $this->db->table('organizational_units')
                ->where('unit_code', $demo['unit_code'])
                ->get()
                ->getFirstRow('array');

            $user = new User([
                'username'               => $demo['username'],
                'email'                  => $demo['email'],
                'password'               => 'ChangeMe!12345',
                'full_name'              => $demo['full_name'],
                'organizational_unit_id' => $unit['id'] ?? null,
            ]);

            $users->save($user);

            $user = $users->findById($users->getInsertID());
            $users->activate($user);
            $user->addGroup($demo['group']);
        }
    }
}
