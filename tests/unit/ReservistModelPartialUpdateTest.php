<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ReservistModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Regression test for a bug found via manual walkthrough:
 * `PromotionOrdersController::record()` updates only `current_rank_id`
 * and `date_of_last_promotion` on the reservist once a promotion order
 * is recorded. When the recorded `effective_date` is "today or later"
 * relative to the server clock at the moment of the *next* day's
 * boundary (e.g. a Records Officer dating the order for tomorrow, or
 * any request that lands right at midnight), `ReservistModel`'s
 * `not_future_date` rule on `date_of_last_promotion` rejects it --
 * `update()` then returns `false` silently (no exception), leaving the
 * reservist's rank stale even though the promotion order and
 * rank-history rows were already inserted. The fix is
 * `skipValidation(true)` for this specific, narrow, system-computed
 * update (a Records Officer legitimately may date an effective
 * promotion for the near future; that is not the same trust boundary
 * as a user directly editing their own profile dates). This test locks
 * in both halves of that behavior so a future refactor can't silently
 * reintroduce the bug.
 *
 * @internal
 */
final class ReservistModelPartialUpdateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function createFullReservist(): int
    {
        return $this->insertRow('reservists', [
            'reservist_number'   => 'RSV-PARTIAL-001',
            'first_name'         => 'Juan',
            'last_name'          => 'Dela Cruz',
            'date_of_enlistment' => '2015-01-01',
            'status'             => 'active',
        ]);
    }

    public function testPartialUpdateWithoutSkipValidationSilentlyFailsForAFutureEffectiveDate(): void
    {
        $reservistId = $this->createFullReservist();

        $rankId = $this->insertRow('ranks', [
            'rank_code'  => 'SGT',
            'rank_name'  => 'Sergeant',
            'sort_order' => 1,
            'is_active'  => 1,
        ]);

        $futureDate = date('Y-m-d', strtotime('+30 days'));

        $model  = new ReservistModel();
        $result = $model->update($reservistId, [
            'current_rank_id'        => $rankId,
            'date_of_last_promotion' => $futureDate,
        ]);

        $this->assertFalse($result, 'Expected not_future_date to reject a future effective date without skipValidation().');

        $unchanged = $model->find($reservistId);
        $this->assertNull($unchanged->current_rank_id);
    }

    public function testPartialUpdateWithSkipValidationSucceedsAndPersistsForAFutureEffectiveDate(): void
    {
        $reservistId = $this->createFullReservist();

        $rankId = $this->insertRow('ranks', [
            'rank_code'  => 'SGT',
            'rank_name'  => 'Sergeant',
            'sort_order' => 1,
            'is_active'  => 1,
        ]);

        $futureDate = date('Y-m-d', strtotime('+30 days'));

        $model  = new ReservistModel();
        $result = $model->skipValidation(true)->update($reservistId, [
            'current_rank_id'        => $rankId,
            'date_of_last_promotion' => $futureDate,
        ]);

        $this->assertTrue($result, 'Expected skipValidation(true) to allow the future-dated update to succeed.');

        $updated = $model->find($reservistId);
        $this->assertSame($rankId, $updated->current_rank_id);
        $this->assertSame($futureDate, $updated->date_of_last_promotion->toDateString());
    }
}
