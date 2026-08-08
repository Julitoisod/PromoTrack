<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\DateDurationCalculator;
use App\Models\PhysicalFitnessTestModel;
use App\Models\PromotionRuleModel;
use App\Models\PromotionRuleVersionModel;
use App\Models\ReservistModel;
use App\Models\ReservistTrainingModel;
use App\Models\TrainingTypeModel;
use App\Services\EligibilityService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Exercises `App\Services\EligibilityService::evaluate()` against real
 * fixture rows in the test database, covering:
 *  - a reservist who meets every configured criterion (fully eligible),
 *  - a reservist who fails an explicit criterion (not eligible), and
 *  - a rank transition with no configured rule (requires manual review,
 *    never silently assumed eligible from time-in-grade alone).
 *
 * @internal
 */
final class EligibilityServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Migrate every namespace (App migrations + Shield) rather than the
     * default `Tests\Support` only namespace, since the service under
     * test depends on the real application schema.
     */
    protected $namespace = null;

    private EligibilityService $service;

    private int $fromRankId;
    private int $toRankId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EligibilityService(
            new ReservistModel(),
            new PromotionRuleModel(),
            new PromotionRuleVersionModel(),
            new ReservistTrainingModel(),
            new TrainingTypeModel(),
            new PhysicalFitnessTestModel(),
            new DateDurationCalculator()
        );

        $this->fromRankId = $this->insertRow('ranks', [
            'rank_code'  => 'CPL',
            'rank_name'  => 'Corporal',
            'sort_order' => 1,
            'is_active'  => 1,
        ]);

        $this->toRankId = $this->insertRow('ranks', [
            'rank_code'  => 'SGT',
            'rank_name'  => 'Sergeant',
            'sort_order' => 2,
            'is_active'  => 1,
        ]);
    }

    /**
     * Inserts a row via the query builder and returns its auto-increment
     * id via `insertID()`. Deliberately bypasses `hasInDatabase()`: that
     * helper returns the boolean result of `insert()` (not the id), and
     * its automatic cleanup deletes rows in insertion order, which
     * violates foreign keys here (parent `ranks` rows would be deleted
     * before dependent `promotion_rules` rows). Since `$refresh = true`
     * already fully drops and recreates the schema before every test,
     * this per-row cleanup is unnecessary anyway.
     */
    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * Inserts a reservist row and returns the new reservist id.
     */
    private function createReservist(string $numberSuffix, ?string $dateOfLastPromotion, ?int $currentRankId): int
    {
        return $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-' . $numberSuffix,
            'first_name'             => 'Juan',
            'last_name'              => 'Dela Cruz',
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => $dateOfLastPromotion,
            'current_rank_id'        => $currentRankId,
            'status'                 => 'active',
        ]);
    }

    /**
     * Inserts an active promotion rule for fromRankId -> toRankId and
     * one effective version, returning the version row id.
     */
    private function createRuleVersion(array $overrides = []): int
    {
        $ruleId = $this->insertRow('promotion_rules', [
            'from_rank_id' => $this->fromRankId,
            'to_rank_id'   => $this->toRankId,
            'rule_name'    => 'Corporal to Sergeant',
            'is_active'    => 1,
        ]);

        return $this->insertRow('promotion_rule_versions', array_merge([
            'promotion_rule_id'              => $ruleId,
            'version_number'                 => 1,
            'minimum_time_in_grade_months'   => 24,
            'requires_mles'                  => 0,
            'requires_physical_fitness_test' => 0,
            'additional_prerequisites'       => null,
            'effective_date'                 => '2020-01-01',
            'expiry_date'                    => null,
            'is_active'                      => 1,
        ], $overrides));
    }

    public function testFullyEligibleWhenAllConfiguredCriteriaAreMet(): void
    {
        $this->createRuleVersion([
            'minimum_time_in_grade_months' => 24,
            'requires_mles'                => 1,
            'requires_physical_fitness_test' => 1,
        ]);

        $mlesTypeId = $this->insertRow('training_types', [
            'training_code' => 'MLES-1',
            'training_name' => 'Mandatory Leadership Education Seminar',
            'is_mles'        => 1,
            'is_active'      => 1,
        ]);

        $reservistId = $this->createReservist('001', '2020-01-01', $this->fromRankId);

        $this->insertRow('reservist_trainings', [
            'reservist_id'      => $reservistId,
            'training_type_id'  => $mlesTypeId,
            'training_title'    => 'MLES Batch 1',
            'date_completed'    => '2021-01-01',
        ]);

        $this->insertRow('physical_fitness_tests', [
            'reservist_id' => $reservistId,
            'test_date'    => '2022-01-01',
            'result'       => 'pass',
        ]);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertTrue($result['is_eligible']);
        $this->assertFalse($result['requires_manual_review']);
        $this->assertSame([], $result['blocking_reasons']);
        $this->assertSame(36, $result['time_in_grade_months']);
    }

    public function testNotEligibleWhenTimeInGradeCriterionFails(): void
    {
        $this->createRuleVersion([
            'minimum_time_in_grade_months' => 24,
        ]);

        $reservistId = $this->createReservist('002', '2022-06-01', $this->fromRankId);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertFalse($result['is_eligible']);
        $this->assertNotSame([], $result['blocking_reasons']);

        $timeInGradeCriterion = array_values(array_filter(
            $result['criteria'],
            static fn (array $criterion): bool => $criterion['key'] === 'time_in_grade'
        ))[0];

        $this->assertFalse($timeInGradeCriterion['met']);
    }

    public function testNotEligibleWhenPhysicalFitnessTestFailed(): void
    {
        $this->createRuleVersion([
            'minimum_time_in_grade_months'   => 12,
            'requires_physical_fitness_test' => 1,
        ]);

        $reservistId = $this->createReservist('003', '2020-01-01', $this->fromRankId);

        $this->insertRow('physical_fitness_tests', [
            'reservist_id' => $reservistId,
            'test_date'    => '2022-01-01',
            'result'       => 'fail',
        ]);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertFalse($result['is_eligible']);

        $pftCriterion = array_values(array_filter(
            $result['criteria'],
            static fn (array $criterion): bool => $criterion['key'] === 'pft'
        ))[0];

        $this->assertFalse($pftCriterion['met']);
    }

    public function testRequiresManualReviewWhenNoRuleIsConfiguredForTransition(): void
    {
        $reservistId = $this->createReservist('004', '2020-01-01', $this->fromRankId);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertFalse($result['is_eligible']);
        $this->assertSame([], $result['criteria']);
        $this->assertContains(
            'No active promotion rule is configured for this rank transition.',
            $result['blocking_reasons']
        );
    }

    public function testRequiresManualReviewWhenMinimumTimeInGradeThresholdIsUnconfigured(): void
    {
        $this->createRuleVersion([
            'minimum_time_in_grade_months' => null,
        ]);

        $reservistId = $this->createReservist('005', '2020-01-01', $this->fromRankId);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertTrue($result['requires_manual_review']);

        $timeInGradeCriterion = array_values(array_filter(
            $result['criteria'],
            static fn (array $criterion): bool => $criterion['key'] === 'time_in_grade'
        ))[0];

        $this->assertNull($timeInGradeCriterion['met']);
    }

    public function testPromotionStatusIsNotYetEligibleWhenTimeInGradeIsBelowThreshold(): void
    {
        $this->createRuleVersion(['minimum_time_in_grade_months' => 24]);

        $reservistId = $this->createReservist('006', '2022-01-01', $this->fromRankId);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertSame('not_yet_eligible', $result['promotion_status']);
        $this->assertSame(12, $result['time_in_grade_months']);

        $timeInGradeCriterion = array_values(array_filter(
            $result['criteria'],
            static fn (array $criterion): bool => $criterion['key'] === 'time_in_grade'
        ))[0];

        $this->assertSame(12, $timeInGradeCriterion['remaining_months']);
        $this->assertNull($timeInGradeCriterion['overdue_months']);
    }

    public function testPromotionStatusIsEligibleWhenTimeInGradeExactlyMeetsThreshold(): void
    {
        $this->createRuleVersion(['minimum_time_in_grade_months' => 24]);

        $reservistId = $this->createReservist('007', '2021-01-01', $this->fromRankId);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertSame('eligible', $result['promotion_status']);

        $timeInGradeCriterion = array_values(array_filter(
            $result['criteria'],
            static fn (array $criterion): bool => $criterion['key'] === 'time_in_grade'
        ))[0];

        $this->assertNull($timeInGradeCriterion['remaining_months']);
        $this->assertNull($timeInGradeCriterion['overdue_months']);
    }

    public function testPromotionStatusIsOverdueWhenTimeInGradeExceedsThreshold(): void
    {
        $this->createRuleVersion(['minimum_time_in_grade_months' => 24]);

        $reservistId = $this->createReservist('008', '2018-01-01', $this->fromRankId);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        $this->assertSame('overdue', $result['promotion_status']);

        $timeInGradeCriterion = array_values(array_filter(
            $result['criteria'],
            static fn (array $criterion): bool => $criterion['key'] === 'time_in_grade'
        ))[0];

        $this->assertSame(36, $timeInGradeCriterion['overdue_months']);
        $this->assertNull($timeInGradeCriterion['remaining_months']);
    }

    public function testEducationTrackSpecificVersionIsPreferredOverTrackAgnosticVersion(): void
    {
        $generalTrackId = $this->insertRow('education_tracks', [
            'track_code' => 'GEN-TEST',
            'track_name' => 'General (Test)',
            'is_active'  => 1,
        ]);
        $rotcTrackId = $this->insertRow('education_tracks', [
            'track_code' => 'ROTC-TEST',
            'track_name' => 'ROTC Professional (Test)',
            'is_active'  => 1,
        ]);

        $ruleId = $this->insertRow('promotion_rules', [
            'from_rank_id' => $this->fromRankId,
            'to_rank_id'   => $this->toRankId,
            'rule_name'    => 'Corporal to Sergeant',
            'is_active'    => 1,
        ]);

        // General track: 24 months required.
        $this->insertRow('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'education_track_id'           => $generalTrackId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 24,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);

        // ROTC Professional track: 12 months required.
        $this->insertRow('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'education_track_id'           => $rotcTrackId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => 12,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);

        $reservistId = $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-009',
            'first_name'             => 'Juan',
            'last_name'              => 'Dela Cruz',
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => '2022-01-01',
            'current_rank_id'        => $this->fromRankId,
            'education_track_id'     => $rotcTrackId,
            'status'                 => 'active',
        ]);

        $result = $this->service->evaluate($reservistId, $this->toRankId, '2023-01-01');

        // 12 months elapsed; the ROTC-track version (12 required) makes
        // this reservist eligible, whereas the General-track version (24
        // required) would have made them not yet eligible -- proving the
        // track-specific version was the one actually applied.
        $this->assertSame('eligible', $result['promotion_status']);
        $this->assertSame($rotcTrackId, $result['education_track_id']);
    }
}
