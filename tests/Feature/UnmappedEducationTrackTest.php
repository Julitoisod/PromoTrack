<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Seeds\PromotionRuleVersionsSeeder;
use App\Models\PromotionRuleVersionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * A reservist whose education track has no Table 3 row of its own must
 * still be assessed.
 *
 * `resolveEffectiveVersion()` falls back to the version with
 * `education_track_id IS NULL` when the track-specific lookup misses.
 * That fallback was never seeded, so six of the nine education tracks —
 * and every reservist with no track recorded — resolved to no version at
 * all, reported "no promotion rule version is effective", and sat at
 * "Pending" no matter how long they had been in grade. The superadmin
 * saw Pending for everyone and had no way to tell why.
 *
 * @internal
 */
final class UnmappedEducationTrackTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private int $fromRankId;
    private int $toRankId;
    private int $mappedTrackId;
    private int $unmappedTrackId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fromRankId = $this->insert('ranks', ['rank_code' => 'UT-A', 'rank_name' => 'Track Rank A', 'sort_order' => 1, 'is_active' => 1]);
        $this->toRankId   = $this->insert('ranks', ['rank_code' => 'UT-B', 'rank_name' => 'Track Rank B', 'sort_order' => 2, 'is_active' => 1]);

        $this->mappedTrackId   = $this->insert('education_tracks', ['track_code' => 'UT-MAPPED', 'track_name' => 'Mapped Track', 'is_active' => 1]);
        $this->unmappedTrackId = $this->insert('education_tracks', ['track_code' => 'UT-UNMAPPED', 'track_name' => 'Unmapped Track', 'is_active' => 1]);

        $ruleId = $this->insert('promotion_rules', [
            'rule_name'    => 'Unmapped track test rule',
            'from_rank_id' => $this->fromRankId,
            'to_rank_id'   => $this->toRankId,
            'is_active'    => 1,
        ]);

        // Only the mapped track gets its own row, mirroring Table 3 covering
        // three of nine tracks.
        $this->insert('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'education_track_id'           => $this->mappedTrackId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 12,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);

        // The baseline the resolver falls back to. This is the row whose
        // absence was the bug.
        $this->insert('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'education_track_id'           => null,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 24,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);

        $this->ruleId = $ruleId;
    }

    private int $ruleId;

    private function insert(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    public function testAnUnmappedTrackFallsBackToTheBaselineVersion(): void
    {
        $version = (new PromotionRuleVersionModel())
            ->resolveEffectiveVersion($this->ruleId, date('Y-m-d'), $this->unmappedTrackId);

        $this->assertNotNull($version, 'An unmapped education track resolved to no rule version at all.');
        $this->assertSame(24, (int) $version['minimum_time_in_grade_months']);
        $this->assertNull($version['education_track_id']);
    }

    public function testAReservistWithNoTrackAtAllStillResolves(): void
    {
        $version = (new PromotionRuleVersionModel())
            ->resolveEffectiveVersion($this->ruleId, date('Y-m-d'), null);

        $this->assertNotNull($version, 'A reservist with no education track resolved to no rule version.');
        $this->assertSame(24, (int) $version['minimum_time_in_grade_months']);
    }

    public function testAMappedTrackStillWinsOverTheBaseline(): void
    {
        $version = (new PromotionRuleVersionModel())
            ->resolveEffectiveVersion($this->ruleId, date('Y-m-d'), $this->mappedTrackId);

        $this->assertNotNull($version);
        $this->assertSame(12, (int) $version['minimum_time_in_grade_months'], 'The baseline overrode a track that has its own row.');
        $this->assertSame($this->mappedTrackId, (int) $version['education_track_id']);
    }

    /**
     * The seeder is what puts the baseline there on a real install, so it
     * is asserted directly: one track-agnostic row per promotion rule.
     */
    public function testTheSeederLeavesABaselineForEveryRule(): void
    {
        $this->db->table('promotion_rule_versions')->where('education_track_id', null)->delete();

        (new PromotionRuleVersionsSeeder(config('Database')))->run();

        // The invariant: any rule the seeder gave track-specific versions to
        // must also have the track-agnostic baseline. Rules outside Table 3
        // (this test's own fixture) are not the seeder's to fill in.
        $rulesWithoutBaseline = $this->db->query(
            'SELECT COUNT(*) AS total FROM promotion_rules r
             WHERE EXISTS (
                 SELECT 1 FROM promotion_rule_versions v
                 WHERE v.promotion_rule_id = r.id AND v.education_track_id IS NOT NULL
             )
             AND NOT EXISTS (
                 SELECT 1 FROM promotion_rule_versions v
                 WHERE v.promotion_rule_id = r.id AND v.education_track_id IS NULL
             )
             AND r.id != ?',
            [$this->ruleId]
        )->getRowArray();

        $this->assertSame(
            0,
            (int) $rulesWithoutBaseline['total'],
            'Some promotion rules have no track-agnostic baseline, so reservists on an unmapped track will show Pending.'
        );
    }
}
