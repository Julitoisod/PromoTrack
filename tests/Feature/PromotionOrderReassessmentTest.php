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
 * Recording a promotion order must leave the reservist's promotion
 * standing correct.
 *
 * The rank change is what invalidates the assessment that justified the
 * package. Before `PromotionOrdersController::reassessAgainstNewRank()`
 * existed, the "overdue" assessment stayed the newest row on file, so a
 * reservist promoted this morning still read as Overdue on the roster,
 * in the promotion backlog report, and in the dashboard's Overdue tile
 * until somebody pressed Assess on them by hand.
 *
 * @internal
 */
final class PromotionOrderReassessmentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;

    private int $unitId;
    private int $reservistId;
    private int $packageId;
    private int $fromRankId;
    private int $toRankId;
    private int $onwardRankId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FiltersConfig $filtersConfig */
        $filtersConfig = config(FiltersConfig::class);
        $filtersConfig->globals['before'] = array_values(array_diff(
            $filtersConfig->globals['before'],
            ['csrf']
        ));

        $this->unitId = $this->insert('organizational_units', [
            'unit_code' => 'PO-UNIT',
            'unit_name' => 'Promotion Order Test Unit',
            'is_active' => 1,
        ]);

        // Three ranks, so the reservist has somewhere to be promoted TO and
        // a further transition to be re-assessed against afterwards.
        $this->fromRankId   = $this->insertRank('PO-A', 'Rank A', 1);
        $this->toRankId     = $this->insertRank('PO-B', 'Rank B', 2);
        $this->onwardRankId = $this->insertRank('PO-C', 'Rank C', 3);

        // A -> B is what this package promotes into; B -> C is the rule the
        // re-assessment must pick up once the order lands.
        $this->insertRuleWithVersion($this->fromRankId, $this->toRankId, 24);
        $this->insertRuleWithVersion($this->toRankId, $this->onwardRankId, 24);

        // Long overdue against A -> B: last promoted eight years ago.
        $this->reservistId = $this->insert('reservists', [
            'reservist_number'       => 'RSV-PO-1',
            'first_name'             => 'Order',
            'last_name'              => 'Tester',
            'date_of_enlistment'     => '2010-01-01',
            'date_of_last_promotion' => '2016-01-01',
            'current_rank_id'        => $this->fromRankId,
            'organizational_unit_id' => $this->unitId,
            'status'                 => 'active',
        ]);

        $assessmentId = $this->insert('promotion_assessments', [
            'reservist_id'         => $this->reservistId,
            'from_rank_id'         => $this->fromRankId,
            'to_rank_id'           => $this->toRankId,
            'as_of_date'           => date('Y-m-d'),
            'time_in_grade_months' => 120,
            'promotion_status'     => 'overdue',
            'is_eligible'          => 1,
            'snapshot_json'        => '{}',
        ]);

        // Parked at `approved`: the one status from which an order is recorded.
        $this->packageId = $this->insert('promotion_packages', [
            'reservist_id'            => $this->reservistId,
            'promotion_assessment_id' => $assessmentId,
            'organizational_unit_id'  => $this->unitId,
            'package_number'          => 'PKG-PO-1',
            'status'                  => 'approved',
            'current_routing_level'   => 'EXTERNAL',
            'open_marker'             => 1,
        ]);
    }

    protected function tearDown(): void
    {
        auth()->logout();
        session()->destroy();

        parent::tearDown();
    }

    private function insert(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function insertRank(string $code, string $name, int $order): int
    {
        return $this->insert('ranks', [
            'rank_code'  => $code,
            'rank_name'  => $name,
            'sort_order' => $order,
            'is_active'  => 1,
        ]);
    }

    private function insertRuleWithVersion(int $fromRankId, int $toRankId, int $minimumMonths): void
    {
        $ruleId = $this->insert('promotion_rules', [
            'rule_name'    => 'PO test rule ' . $fromRankId . '->' . $toRankId,
            'from_rank_id' => $fromRankId,
            'to_rank_id'   => $toRankId,
            'is_active'    => 1,
        ]);

        $this->insert('promotion_rule_versions', [
            'promotion_rule_id'            => $ruleId,
            'version_number'               => 1,
            'minimum_time_in_grade_months' => $minimumMonths,
            'effective_date'               => '2020-01-01',
            'is_active'                    => 1,
        ]);
    }

    private function recordsOfficer(): User
    {
        $users = auth()->getProvider();

        $user = new User([
            'username'               => 'po_records',
            'email'                  => 'po_records@promotrack.local',
            'password'               => 'OrderPass!12345',
            'full_name'              => 'Promotion Order Tester',
            'organizational_unit_id' => $this->unitId,
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $users->activate($user);
        $user->addGroup('records_officer');

        return $user;
    }

    private function latestStatus(): ?string
    {
        $row = $this->db->table('promotion_assessments')
            ->where('reservist_id', $this->reservistId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        return $row['promotion_status'] ?? null;
    }

    public function testRecordingAPromotionOrderClearsTheOverdueStanding(): void
    {
        $this->assertSame('overdue', $this->latestStatus(), 'Fixture did not start overdue.');

        $this->actingAs($this->recordsOfficer());

        $this->post('/records/packages/' . $this->packageId . '/promotion-order', [
            'order_date'     => date('Y-m-d'),
            'effective_date' => date('Y-m-d'),
        ]);

        // The rank moved...
        $this->seeInDatabase('reservists', [
            'id'              => $this->reservistId,
            'current_rank_id' => $this->toRankId,
        ]);

        // ...and the standing moved with it, measured against the NEXT rank.
        $this->assertSame(
            'not_yet_eligible',
            $this->latestStatus(),
            'The reservist still reads as overdue after being promoted.'
        );

        $this->seeInDatabase('promotion_assessments', [
            'reservist_id' => $this->reservistId,
            'to_rank_id'   => $this->onwardRankId,
        ]);
    }

    public function testAReservistAtTheTopOfTheTrackIsLeftPendingRatherThanEligible(): void
    {
        // Remove the onward rule: B is now the end of the line.
        $this->db->table('promotion_rules')
            ->where('from_rank_id', $this->toRankId)
            ->delete();

        $this->actingAs($this->recordsOfficer());

        $this->post('/records/packages/' . $this->packageId . '/promotion-order', [
            'order_date'     => date('Y-m-d'),
            'effective_date' => date('Y-m-d'),
        ]);

        $this->seeInDatabase('reservists', [
            'id'              => $this->reservistId,
            'current_rank_id' => $this->toRankId,
        ]);

        // No onward rule means no new assessment is invented; the newest row
        // is still the one that justified this package.
        $this->assertSame(1, $this->db->table('promotion_assessments')
            ->where('reservist_id', $this->reservistId)
            ->countAllResults());
    }
}
