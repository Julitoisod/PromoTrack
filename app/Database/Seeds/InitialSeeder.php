<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Master seeder that provisions the minimum reference data and initial
 * administrator account required to start using PromoTrack AFP-RF.
 *
 * Run with:
 *   php spark db:seed InitialSeeder
 *
 * Note: Shield groups and permissions (superadmin, system_administrator,
 * unit_s1_clerk, cdc_administrator, rcdg_reviewer, commander_viewer,
 * records_officer) are defined declaratively in `app/Config/AuthGroups.php`
 * and are not stored in the database, so no seeder is required for them.
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OrganizationalUnitsSeeder::class);
        $this->call(RanksSeeder::class);
        $this->call(EducationTracksSeeder::class);
        $this->call(TrainingTypesSeeder::class);
        $this->call(PromotionRulesSeeder::class);
        $this->call(PromotionRuleVersionsSeeder::class);
        $this->call(DocumentRequirementsSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(DemoUsersSeeder::class);
    }
}
