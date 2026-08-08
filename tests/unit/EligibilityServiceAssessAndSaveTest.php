<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\DateDurationCalculator;
use App\Models\AssessmentRequirementModel;
use App\Models\PhysicalFitnessTestModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionRuleModel;
use App\Models\PromotionRuleVersionModel;
use App\Models\ReservistModel;
use App\Models\ReservistTrainingModel;
use App\Models\TrainingTypeModel;
use App\Services\EligibilityService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Exercises `EligibilityService::assessAndSave()`: verifies it persists
 * an immutable `promotion_assessments` snapshot plus one
 * `assessment_requirements` row per criterion.
 *
 * @internal
 */
final class EligibilityServiceAssessAndSaveTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private EligibilityService $service;
    private PromotionAssessmentModel $promotionAssessmentModel;
    private AssessmentRequirementModel $assessmentRequirementModel;

    private int $fromRankId;
    private int $toRankId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionAssessmentModel   = new PromotionAssessmentModel();
        $this->assessmentRequirementModel = new AssessmentRequirementModel();

        $this->service = new EligibilityService(
            new ReservistModel(),
            new PromotionRuleModel(),
            new PromotionRuleVersionModel(),
            new ReservistTrainingModel(),
            new TrainingTypeModel(),
            new PhysicalFitnessTestModel(),
            new DateDurationCalculator(),
            $this->promotionAssessmentModel,
            $this->assessmentRequirementModel,
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

        $ruleId = $this->insertRow('promotion_rules', [
            'from_rank_id' => $this->fromRankId,
            'to_rank_id'   => $this->toRankId,
            'rule_name'    => 'Corporal to Sergeant',
            'is_active'    => 1,
        ]);

        $this->insertRow('promotion_rule_versions', [
            'promotion_rule_id'              => $ruleId,
            'version_number'                 => 1,
            'minimum_time_in_grade_months'   => 24,
            'requires_mles'                  => 0,
            'requires_physical_fitness_test' => 0,
            'effective_date'                 => '2020-01-01',
            'is_active'                      => 1,
        ]);
    }

    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    public function testAssessAndSavePersistsAnAssessmentAndItsRequirements(): void
    {
        $reservistId = $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-100',
            'first_name'             => 'Juan',
            'last_name'              => 'Dela Cruz',
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => '2018-01-01',
            'current_rank_id'        => $this->fromRankId,
            'status'                 => 'active',
        ]);

        $assessorUserId = 1;

        $result = $this->service->assessAndSave($reservistId, $this->toRankId, $assessorUserId, '2023-01-01');

        $this->assertArrayHasKey('assessment_id', $result);
        $this->assertGreaterThan(0, $result['assessment_id']);
        $this->assertSame('overdue', $result['promotion_status']);

        $persisted = $this->promotionAssessmentModel->find($result['assessment_id']);

        $this->assertNotNull($persisted);
        $this->assertSame($reservistId, (int) $persisted['reservist_id']);
        $this->assertSame($this->toRankId, (int) $persisted['to_rank_id']);
        $this->assertSame('overdue', $persisted['promotion_status']);
        $this->assertSame($assessorUserId, (int) $persisted['assessed_by']);
        $this->assertNull($persisted['verified_at']);

        $decodedSnapshot = json_decode((string) $persisted['snapshot_json'], true);
        $this->assertSame('overdue', $decodedSnapshot['promotion_status']);

        $requirements = $this->assessmentRequirementModel->forAssessment($result['assessment_id']);
        $this->assertNotEmpty($requirements);
        $this->assertSame('time_in_grade', $requirements[0]['requirement_code']);
    }

    public function testMostRecentForReturnsTheLatestAssessment(): void
    {
        $reservistId = $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-101',
            'first_name'             => 'Pedro',
            'last_name'              => 'Santos',
            'date_of_enlistment'     => '2015-01-01',
            'date_of_last_promotion' => '2021-01-01',
            'current_rank_id'        => $this->fromRankId,
            'status'                 => 'active',
        ]);

        $this->service->assessAndSave($reservistId, $this->toRankId, 1, '2022-01-01');
        $second = $this->service->assessAndSave($reservistId, $this->toRankId, 1, '2023-06-01');

        $mostRecent = $this->promotionAssessmentModel->mostRecentFor($reservistId, $this->toRankId);

        $this->assertSame($second['assessment_id'], (int) $mostRecent['id']);
    }
}
