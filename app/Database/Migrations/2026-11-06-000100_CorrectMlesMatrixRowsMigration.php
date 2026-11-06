<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Re-seats the AFP MLES thresholds (thesis Table 3) one grade up.
 *
 * Version 1 of every Table 3 rule version was seeded from the row of the
 * grade a reservist is promoted FROM. Table 3's rows are the grade being
 * promoted INTO: its E-1 row is "None Required, 0/0/0", which as a
 * from-grade made every Private due for PFC on enlistment day, and a
 * Corporal on the ROTC track overdue at three years instead of eligible.
 * The client's own test records -- CPL/ROTC at exactly 3 years, SGT/ROTC
 * at exactly 4 -- both sit exactly on the INTO row.
 *
 * Thresholds are never edited in place (past assessments cite the version
 * they ran under), so this inserts a new version effective today and
 * expires the old one the day before, exactly as
 * Admin\PromotionRulesController::storeVersion() does. A version whose
 * threshold no longer equals the old seeded figure was changed by an
 * administrator on purpose and is left alone.
 *
 * On a fresh install there are no rules yet when this runs, so it is a
 * no-op and the corrected PromotionRuleVersionsSeeder seeds the right
 * figures directly.
 */
class CorrectMlesMatrixRowsMigration extends Migration
{
    private const NOTE = 'Table 3 row of the grade promoted INTO (corrects version 1, which used the row one grade lower) -- REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.';

    /** Table 3 rows E-1..E-7, in years: General, 1st Year College, ROTC Professional. */
    private const TABLE_3 = [[0, 0, 0], [2, 2, 1], [4, 3, 2], [7, 6, 3], [10, 9, 4], [13, 12, 7], [17, 15, 11]];

    /** The from-ranks version 1 keyed on rows E-1..E-6. */
    private const FROM_RANKS = ['PVT', 'PFC', 'CPL', 'SGT', 'SSG', 'TSG'];

    /** TABLE_3 column per track; the track-agnostic baseline reuses General. */
    private const TRACK_COLUMNS = ['GENERAL' => 0, 'COLLEGE_1STYR' => 1, 'ROTC_PROF' => 2];

    public function up(): void
    {
        $trackIds = array_column(
            $this->db->table('education_tracks')
                ->select('id, track_code')
                ->whereIn('track_code', array_keys(self::TRACK_COLUMNS))
                ->get()
                ->getResultArray(),
            'id',
            'track_code'
        );

        $tracks = [[null, 0]];

        foreach (self::TRACK_COLUMNS as $code => $column) {
            if (isset($trackIds[$code])) {
                $tracks[] = [(int) $trackIds[$code], $column];
            }
        }

        foreach (self::FROM_RANKS as $grade => $fromCode) {
            $rule = $this->db->table('promotion_rules pr')
                ->select('pr.id')
                ->join('ranks r', 'r.id = pr.from_rank_id')
                ->where('r.rank_code', $fromCode)
                ->get()
                ->getFirstRow('array');

            if ($rule === null) {
                continue;
            }

            foreach ($tracks as [$trackId, $column]) {
                $this->supersede(
                    (int) $rule['id'],
                    $trackId,
                    self::TABLE_3[$grade][$column] * 12,
                    self::TABLE_3[$grade + 1][$column] * 12
                );
            }
        }
    }

    public function down(): void
    {
        $corrections = $this->db->table('promotion_rule_versions')
            ->where('notes', self::NOTE)
            ->get()
            ->getResultArray();

        foreach ($corrections as $correction) {
            $this->trackScoped($correction['promotion_rule_id'], $correction['education_track_id'])
                ->where('expiry_date', date('Y-m-d', strtotime($correction['effective_date'] . ' -1 day')))
                ->update(['expiry_date' => null]);

            $this->db->table('promotion_rule_versions')->where('id', $correction['id'])->delete();
        }
    }

    private function supersede(int $ruleId, ?int $trackId, int $oldMonths, int $newMonths): void
    {
        $today = date('Y-m-d');

        $current = $this->trackScoped($ruleId, $trackId)
            ->where('is_active', 1)
            ->where('effective_date <=', $today)
            ->groupStart()
                ->where('expiry_date', null)
                ->orWhere('expiry_date >=', $today)
            ->groupEnd()
            ->orderBy('effective_date', 'DESC')
            ->orderBy('version_number', 'DESC')
            ->get(1)
            ->getFirstRow('array');

        if ($current === null || (int) $current['minimum_time_in_grade_months'] !== $oldMonths) {
            return;
        }

        $latest = $this->db->table('promotion_rule_versions')
            ->selectMax('version_number', 'highest')
            ->where('promotion_rule_id', $ruleId)
            ->get()
            ->getFirstRow('array');

        $now = date('Y-m-d H:i:s');

        $this->db->table('promotion_rule_versions')->insert([
            'promotion_rule_id'              => $ruleId,
            'education_track_id'             => $trackId,
            'version_number'                 => (int) $latest['highest'] + 1,
            'minimum_time_in_grade_months'   => $newMonths,
            'requires_mles'                  => $current['requires_mles'],
            'requires_physical_fitness_test' => $current['requires_physical_fitness_test'],
            'additional_prerequisites'       => $current['additional_prerequisites'],
            'effective_date'                 => $today,
            'expiry_date'                    => null,
            'is_active'                      => 1,
            'notes'                          => self::NOTE,
            'created_at'                     => $now,
            'updated_at'                     => $now,
        ]);

        $this->db->table('promotion_rule_versions')
            ->where('id', $current['id'])
            ->update(['expiry_date' => date('Y-m-d', strtotime('-1 day')), 'updated_at' => $now]);
    }

    private function trackScoped(int|string $ruleId, int|string|null $trackId): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('promotion_rule_versions')
            ->where('promotion_rule_id', $ruleId)
            ->where('education_track_id', $trackId);
    }
}
