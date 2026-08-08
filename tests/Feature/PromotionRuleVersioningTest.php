<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;

/**
 * Covers Admin\PromotionRulesController.
 *
 * The behaviour worth protecting is that changing a threshold *supersedes*
 * rather than overwrites: the old version keeps its row and gains an expiry,
 * so an assessment computed under the old thresholds can still be explained.
 * If that ever regresses into an UPDATE, past assessments silently start
 * citing rules that were not in force when they ran.
 *
 * @internal
 */
final class PromotionRuleVersioningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private User $admin;
    private int $privateRankId;
    private int $corporalRankId;
    private int $sergeantRankId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->privateRankId  = $this->insertRank('PVT', 'Private', 1);
        $this->corporalRankId = $this->insertRank('CPL', 'Corporal', 2);
        $this->sergeantRankId = $this->insertRank('SGT', 'Sergeant', 3);

        $users = auth()->getProvider();

        $user = new User([
            'username'  => 'ruleadmin',
            'email'     => 'ruleadmin@promotrack.local',
            'password'  => 'RulePass!12345',
            'full_name' => 'Rule Test Administrator',
        ]);

        $users->save($user);

        $this->admin = $users->findById($users->getInsertID());
        $users->activate($this->admin);
        $this->admin->addGroup('superadmin');
    }

    protected function tearDown(): void
    {
        auth()->logout();
        session()->destroy();

        parent::tearDown();
    }

    private function insertRank(string $code, string $name, int $order): int
    {
        $this->db->table('ranks')->insert([
            'rank_code'     => $code,
            'rank_name'     => $name,
            'rank_category' => 'enlisted',
            'sort_order'    => $order,
            'is_active'     => 1,
        ]);

        return (int) $this->db->insertID();
    }

    private function createRule(string $name = 'Private to Corporal'): int
    {
        $this->post('/admin/rules', [
            'from_rank_id' => $this->privateRankId,
            'to_rank_id'   => $this->corporalRankId,
            'rule_name'    => $name,
        ]);

        $row = $this->db->table('promotion_rules')->where('rule_name', $name)->get()->getRowArray();

        return (int) $row['id'];
    }

    public function testAnAdministratorCanCreateARankTransitionRule(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/rules', [
            'from_rank_id' => $this->privateRankId,
            'to_rank_id'   => $this->corporalRankId,
            'rule_name'    => 'Private to Corporal',
        ])->assertRedirect();

        $this->seeInDatabase('promotion_rules', [
            'from_rank_id' => $this->privateRankId,
            'to_rank_id'   => $this->corporalRankId,
            'is_active'    => 1,
        ]);
    }

    public function testADuplicateRankTransitionIsRejectedAsValidationNotAsACrash(): void
    {
        $this->actingAs($this->admin);
        $this->createRule();

        $result = $this->post('/admin/rules', [
            'from_rank_id' => $this->privateRankId,
            'to_rank_id'   => $this->corporalRankId,
            'rule_name'    => 'Duplicate transition',
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error');

        $this->dontSeeInDatabase('promotion_rules', ['rule_name' => 'Duplicate transition']);
    }

    public function testADowngradeIsRejected(): void
    {
        $this->actingAs($this->admin);

        $result = $this->post('/admin/rules', [
            'from_rank_id' => $this->sergeantRankId,
            'to_rank_id'   => $this->privateRankId,
            'rule_name'    => 'Backwards',
        ]);

        $result->assertRedirect();
        $this->dontSeeInDatabase('promotion_rules', ['rule_name' => 'Backwards']);
    }

    public function testTheFirstVersionBecomesTheOneInForce(): void
    {
        $this->actingAs($this->admin);
        $ruleId = $this->createRule();

        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '24',
            'effective_date'               => '2026-01-01',
            'requires_mles'                => '1',
        ])->assertRedirect();

        $this->seeInDatabase('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 24,
            'expiry_date'                  => null,
        ]);
    }

    public function testASecondVersionSupersedesTheFirstWithoutOverwritingIt(): void
    {
        $this->actingAs($this->admin);
        $ruleId = $this->createRule();

        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '24',
            'effective_date'               => '2026-01-01',
        ]);

        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '36',
            'effective_date'               => '2026-07-01',
        ]);

        // The superseded version is still there, with its original threshold
        // and an expiry the day before the new one starts.
        $this->seeInDatabase('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 24,
            'expiry_date'                  => '2026-06-30',
        ]);

        $this->seeInDatabase('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'version_number'               => 2,
            'minimum_time_in_grade_months' => 36,
            'expiry_date'                  => null,
        ]);

        $count = $this->db->table('promotion_rule_versions')
            ->where('promotion_rule_id', $ruleId)
            ->countAllResults();

        $this->assertSame(2, $count, 'Superseding a version must not delete or overwrite the previous one.');
    }

    public function testTheVersionInForceDependsOnTheDateItIsResolvedFor(): void
    {
        $this->actingAs($this->admin);
        $ruleId = $this->createRule();

        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '24',
            'effective_date'               => '2026-01-01',
        ]);
        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '36',
            'effective_date'               => '2026-07-01',
        ]);

        $versions = model(\App\Models\PromotionRuleVersionModel::class);

        $inMarch = $versions->resolveEffectiveVersion($ruleId, '2026-03-15');
        $inAugust = $versions->resolveEffectiveVersion($ruleId, '2026-08-15');

        $this->assertSame(24, (int) $inMarch['minimum_time_in_grade_months']);
        $this->assertSame(36, (int) $inAugust['minimum_time_in_grade_months']);
    }

    public function testATrackSpecificVersionDoesNotExpireTheAllTracksVersion(): void
    {
        $this->actingAs($this->admin);
        $ruleId = $this->createRule();

        $this->db->table('education_tracks')->insert([
            'track_code' => 'TRK-A',
            'track_name' => 'Track A',
            'is_active'  => 1,
        ]);
        $trackId = (int) $this->db->insertID();

        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '24',
            'effective_date'               => '2026-01-01',
        ]);

        $this->post('/admin/rules/' . $ruleId . '/versions', [
            'minimum_time_in_grade_months' => '18',
            'effective_date'               => '2026-07-01',
            'education_track_id'           => $trackId,
        ]);

        // The all-tracks version still governs everyone it did before.
        $this->seeInDatabase('promotion_rule_versions', [
            'promotion_rule_id'  => $ruleId,
            'education_track_id' => null,
            'expiry_date'        => null,
        ]);
    }
}
