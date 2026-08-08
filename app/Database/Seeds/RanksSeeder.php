<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the standard AFP/Philippine Army rank names, in ascending
 * `sort_order`, so promotion packages have a rank hierarchy to work
 * against during development and testing.
 *
 * The rank names themselves (enlisted, non-commissioned, and
 * commissioned officer titles) are well-established public
 * designations. However, the exact mapping of each rank to a
 * `rank_category`, and any rank-to-rank promotion eligibility,
 * time-in-grade, or MLES rule tied to a specific rank,
 * REQUIRE OFFICIAL AFP/PHILIPPINE ARMY VALIDATION before being relied
 * upon for an actual promotion decision.
 */
class RanksSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $ranks = [
            ['rank_code' => 'PVT', 'rank_name' => 'Private', 'rank_category' => 'enlisted', 'sort_order' => 10],
            ['rank_code' => 'PFC', 'rank_name' => 'Private First Class', 'rank_category' => 'enlisted', 'sort_order' => 20],
            ['rank_code' => 'CPL', 'rank_name' => 'Corporal', 'rank_category' => 'enlisted', 'sort_order' => 30],
            ['rank_code' => 'SGT', 'rank_name' => 'Sergeant', 'rank_category' => 'non-commissioned', 'sort_order' => 40],
            ['rank_code' => 'SSG', 'rank_name' => 'Staff Sergeant', 'rank_category' => 'non-commissioned', 'sort_order' => 50],
            ['rank_code' => 'TSG', 'rank_name' => 'Technical Sergeant', 'rank_category' => 'non-commissioned', 'sort_order' => 60],
            ['rank_code' => 'MSG', 'rank_name' => 'Master Sergeant', 'rank_category' => 'non-commissioned', 'sort_order' => 70],
            ['rank_code' => '2LT', 'rank_name' => 'Second Lieutenant', 'rank_category' => 'commissioned', 'sort_order' => 80],
            ['rank_code' => '1LT', 'rank_name' => 'First Lieutenant', 'rank_category' => 'commissioned', 'sort_order' => 90],
            ['rank_code' => 'CPT', 'rank_name' => 'Captain', 'rank_category' => 'commissioned', 'sort_order' => 100],
            ['rank_code' => 'MAJ', 'rank_name' => 'Major', 'rank_category' => 'commissioned', 'sort_order' => 110],
            ['rank_code' => 'LTC', 'rank_name' => 'Lieutenant Colonel', 'rank_category' => 'commissioned', 'sort_order' => 120],
            ['rank_code' => 'COL', 'rank_name' => 'Colonel', 'rank_category' => 'commissioned', 'sort_order' => 130],
        ];

        foreach ($ranks as $rank) {
            $this->db->table('ranks')->insert([
                'rank_code'     => $rank['rank_code'],
                'rank_name'     => $rank['rank_name'],
                'rank_category' => $rank['rank_category'],
                'sort_order'    => $rank['sort_order'],
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
