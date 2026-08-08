<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds one promotion_rule_versions row per (rank transition, education
 * track) pair, encoding Table 3 (AFP Minimum Length of Enlisted Status,
 * in years) of the PromoTrack AFP-RF thesis, Chapter 1. Source: "AFP
 * Reserve Force Promotion Regulations as presented in the 1502 CDC
 * advocacy materials (2026)" -- every threshold below therefore
 * REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION before being relied
 * upon for an actual promotion decision; see `PromotionRulesSeeder` for
 * the grade-to-rank-title mapping this seeder depends on.
 *
 * Run only after `RanksSeeder`, `EducationTracksSeeder`, and
 * `PromotionRulesSeeder`.
 */
class PromotionRuleVersionsSeeder extends Seeder
{
    /**
     * Table 3 thresholds in whole years, keyed by the "from" rank code,
     * for the General / Completed 1st Year College / ROTC Professional
     * tracks respectively.
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    private const MLES_YEARS_BY_FROM_RANK = [
        'PVT' => [0, 0, 0],   // E-1
        'PFC' => [2, 2, 1],   // E-2
        'CPL' => [4, 3, 2],   // E-3
        'SGT' => [7, 6, 3],   // E-4
        'SSG' => [10, 9, 4],  // E-5
        'TSG' => [13, 12, 7], // E-6
    ];

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $rules = $this->db->table('promotion_rules pr')
            ->select('pr.id AS rule_id, fr.rank_code AS from_code')
            ->join('ranks fr', 'fr.id = pr.from_rank_id')
            ->get()
            ->getResultArray();

        $tracks = $this->db->table('education_tracks')
            ->select('id, track_code')
            ->whereIn('track_code', ['GENERAL', 'COLLEGE_1STYR', 'ROTC_PROF'])
            ->get()
            ->getResultArray();

        $trackIdsByCode = array_column($tracks, 'id', 'track_code');
        $trackOrder     = ['GENERAL', 'COLLEGE_1STYR', 'ROTC_PROF'];

        foreach ($rules as $rule) {
            $yearsByTrack = self::MLES_YEARS_BY_FROM_RANK[$rule['from_code']] ?? null;

            if ($yearsByTrack === null) {
                continue;
            }

            foreach ($trackOrder as $index => $trackCode) {
                $educationTrackId = $trackIdsByCode[$trackCode] ?? null;

                if ($educationTrackId === null) {
                    continue;
                }

                $exists = $this->db->table('promotion_rule_versions')
                    ->where('promotion_rule_id', $rule['rule_id'])
                    ->where('education_track_id', $educationTrackId)
                    ->where('version_number', 1)
                    ->get()
                    ->getFirstRow('array');

                if ($exists !== null) {
                    continue;
                }

                $this->db->table('promotion_rule_versions')->insert([
                    'promotion_rule_id'              => $rule['rule_id'],
                    'education_track_id'             => $educationTrackId,
                    'version_number'                 => 1,
                    'minimum_time_in_grade_months'   => $yearsByTrack[$index] * 12,
                    'requires_mles'                  => 0,
                    'requires_physical_fitness_test' => 0,
                    'additional_prerequisites'       => null,
                    'effective_date'                 => '2026-01-01',
                    'expiry_date'                    => null,
                    'is_active'                      => 1,
                    'notes'                          => 'Table 3 (AFP MLES matrix) -- REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.',
                    'created_at'                      => $now,
                    'updated_at'                      => $now,
                ]);
            }
        }
    }
}
