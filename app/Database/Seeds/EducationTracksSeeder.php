<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds educational-attainment classifications used on the reservist
 * profile.
 *
 * The GENERAL / COLLEGE_1STYR / ROTC_PROF rows are the three tracks the
 * AFP Minimum Length of Enlisted Status (MLES) matrix keys its
 * time-in-grade thresholds to (see `PromotionRuleVersionsSeeder`); the
 * remaining rows are broader, generic education categories used for
 * display/reporting only and carry no MLES rule of their own.
 */
class EducationTracksSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $tracks = [
            // MLES-matrix tracks (Table 3): drives promotion_rule_versions.
            ['track_code' => 'GENERAL', 'track_name' => 'General (High School Graduate)', 'description' => 'MLES matrix: General track'],
            ['track_code' => 'COLLEGE_1STYR', 'track_name' => 'Completed 1st Year College', 'description' => 'MLES matrix: Completed 1st Year College track'],
            ['track_code' => 'ROTC_PROF', 'track_name' => 'ROTC Professional', 'description' => 'MLES matrix: ROTC Professional track (completed Advanced ROTC/POTC)'],
            // Generic display-only categories, not tied to an MLES rule.
            ['track_code' => 'ELEM', 'track_name' => 'Elementary Graduate', 'description' => null],
            ['track_code' => 'HS', 'track_name' => 'High School Graduate', 'description' => null],
            ['track_code' => 'VOC', 'track_name' => 'Vocational/Technical Graduate', 'description' => null],
            ['track_code' => 'COLLEGE_UNDERGRAD', 'track_name' => 'College Undergraduate', 'description' => null],
            ['track_code' => 'COLLEGE_GRAD', 'track_name' => 'College Graduate', 'description' => null],
            ['track_code' => 'POST_GRAD', 'track_name' => 'Post-Graduate Degree Holder', 'description' => null],
        ];

        foreach ($tracks as $track) {
            $this->db->table('education_tracks')->insert([
                'track_code'  => $track['track_code'],
                'track_name'  => $track['track_name'],
                'description' => $track['description'],
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
