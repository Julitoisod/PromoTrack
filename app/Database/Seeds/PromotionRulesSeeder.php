<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the enlisted rank-to-rank promotion rules that the AFP Minimum
 * Length of Enlisted Status (MLES) matrix (Table 3 of the PromoTrack
 * AFP-RF thesis, Chapter 1) governs.
 *
 * Rank titles are already seeded by `RanksSeeder` (PVT..MSG). The
 * thesis expresses the matrix in terms of eight enlisted grades
 * (E-1..E-8) without stating which grade corresponds to which rank
 * title; E-7 and E-8 share identical thresholds and this codebase only
 * seeds ranks up to MSG (no rank above it), so PVT is E-1 and the six
 * adjacent rank pairs below are the promotions INTO E-2..E-7 -- each
 * governed by Table 3's row for the grade promoted into (see
 * `PromotionRuleVersionsSeeder`). This
 * grade-to-rank-title mapping is an inference, not a cited fact, and
 * REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION before being relied
 * upon for an actual promotion decision.
 */
class PromotionRulesSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $ranks = $this->db->table('ranks')
            ->select('id, rank_code')
            ->whereIn('rank_code', ['PVT', 'PFC', 'CPL', 'SGT', 'SSG', 'TSG', 'MSG'])
            ->get()
            ->getResultArray();

        $rankIdsByCode = array_column($ranks, 'id', 'rank_code');

        $transitions = [
            ['from' => 'PVT', 'to' => 'PFC', 'name' => 'Private to Private First Class'],
            ['from' => 'PFC', 'to' => 'CPL', 'name' => 'Private First Class to Corporal'],
            ['from' => 'CPL', 'to' => 'SGT', 'name' => 'Corporal to Sergeant'],
            ['from' => 'SGT', 'to' => 'SSG', 'name' => 'Sergeant to Staff Sergeant'],
            ['from' => 'SSG', 'to' => 'TSG', 'name' => 'Staff Sergeant to Technical Sergeant'],
            ['from' => 'TSG', 'to' => 'MSG', 'name' => 'Technical Sergeant to Master Sergeant'],
        ];

        foreach ($transitions as $transition) {
            $fromRankId = $rankIdsByCode[$transition['from']] ?? null;
            $toRankId   = $rankIdsByCode[$transition['to']] ?? null;

            if ($fromRankId === null || $toRankId === null) {
                continue;
            }

            $exists = $this->db->table('promotion_rules')
                ->where('from_rank_id', $fromRankId)
                ->where('to_rank_id', $toRankId)
                ->get()
                ->getFirstRow('array');

            if ($exists !== null) {
                continue;
            }

            $this->db->table('promotion_rules')->insert([
                'from_rank_id' => $fromRankId,
                'to_rank_id'   => $toRankId,
                'rule_name'    => $transition['name'] . ' (AFP MLES)',
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }
}
