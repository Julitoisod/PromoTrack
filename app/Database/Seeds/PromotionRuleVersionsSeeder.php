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
     * Table 3 thresholds in whole years for the General / Completed 1st
     * Year College / ROTC Professional tracks, keyed by the rank a
     * reservist is promoted FROM. Each row is Table 3's row for the grade
     * being promoted INTO: its E-1 row ("None Required", 0/0/0) is the
     * entry grade, not a zero-year wait before a Private makes PFC.
     *
     * Version 1 originally used the row one grade lower;
     * `CorrectMlesMatrixRowsMigration` supersedes it on existing installs.
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    private const MLES_YEARS_BY_FROM_RANK = [
        'PVT' => [2, 2, 1],    // -> PFC, Table 3 E-2
        'PFC' => [4, 3, 2],    // -> CPL, E-3
        'CPL' => [7, 6, 3],    // -> SGT, E-4
        'SGT' => [10, 9, 4],   // -> SSG, E-5
        'SSG' => [13, 12, 7],  // -> TSG, E-6
        'TSG' => [17, 15, 11], // -> MSG, E-7
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

            $this->seedTrackAgnosticBaseline((int) $rule['rule_id'], $yearsByTrack[0] * 12, $now);
        }
    }

    /**
     * Inserts the track-agnostic baseline version for $ruleId: the row
     * `PromotionRuleVersionModel::resolveEffectiveVersion()` falls back
     * to when a reservist's education track has no version of its own.
     *
     * Without it that fallback finds nothing, `evaluate()` reports "no
     * promotion rule version is effective", and the reservist sits at
     * "Pending" forever with nothing on screen explaining why. Table 3
     * covers only three of the nine seeded education tracks, so the
     * other six -- and every reservist with no track recorded at all --
     * fell straight into that hole.
     *
     * The baseline reuses the GENERAL (High School Graduate) figure
     * rather than inventing one. It is the longest of the three, so an
     * unmapped track is never assessed more leniently than the published
     * matrix allows. It still REQUIRES OFFICIAL AFP/PHILIPPINE ARMY
     * VALIDATION, exactly like every other threshold in this seeder.
     */
    private function seedTrackAgnosticBaseline(int $ruleId, int $months, string $now): void
    {
        $exists = $this->db->table('promotion_rule_versions')
            ->where('promotion_rule_id', $ruleId)
            ->where('education_track_id', null)
            ->get()
            ->getFirstRow('array');

        if ($exists !== null) {
            return;
        }

        $this->db->table('promotion_rule_versions')->insert([
            'promotion_rule_id'              => $ruleId,
            'education_track_id'             => null,
            'version_number'                 => 1,
            'minimum_time_in_grade_months'   => $months,
            'requires_mles'                  => 0,
            'requires_physical_fitness_test' => 0,
            'additional_prerequisites'       => null,
            'effective_date'                 => '2026-01-01',
            'expiry_date'                    => null,
            'is_active'                      => 1,
            'notes'                          => 'Default for any education track without its own Table 3 row -- REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.',
            'created_at'                     => $now,
            'updated_at'                     => $now,
        ]);
    }
}
