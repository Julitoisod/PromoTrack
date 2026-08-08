<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PromotionAssessmentModel;
use App\Models\PromotionOrderModel;
use App\Models\PromotionPackageModel;
use App\Models\PromotionRuleModel;
use App\Models\RankHistoryModel;
use App\Models\ReservistModel;
use App\Services\PackageTargetRankResolver;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Records the official promotion order once the external approving
 * authority has approved a package: approved -> promotion_order_recorded
 * -> closed. Per design doc §5.7/§6, recording an order updates the
 * reservist's current rank and appends a `reservist_rank_histories` row
 * in the same DB transaction as the order itself.
 */
class PromotionOrdersController extends BaseController
{
    private PromotionPackageModel $promotionPackageModel;
    private PromotionOrderModel $promotionOrderModel;
    private ReservistModel $reservistModel;
    private RankHistoryModel $rankHistoryModel;
    private PackageTargetRankResolver $targetRankResolver;

    public function __construct()
    {
        $this->promotionPackageModel = new PromotionPackageModel();
        $this->promotionOrderModel   = new PromotionOrderModel();
        $this->reservistModel        = new ReservistModel();
        $this->rankHistoryModel      = new RankHistoryModel();
        $this->targetRankResolver    = new PackageTargetRankResolver(
            new PromotionAssessmentModel(),
            new ReservistModel(),
            new PromotionRuleModel(),
        );
    }

    public function record(?int $packageId = null): RedirectResponse
    {
        if ($packageId === null) {
            throw new RuntimeException('A package id is required.');
        }

        $package = $this->promotionPackageModel->find($packageId);

        if ($package === null) {
            throw new RuntimeException("Promotion package #{$packageId} was not found.");
        }

        $newRankId      = $this->targetRankResolver->resolve($package);
        $orderDate      = trim((string) $this->request->getPost('order_date')) ?: date('Y-m-d');
        $effectiveDate  = trim((string) $this->request->getPost('effective_date')) ?: $orderDate;

        if ($newRankId === null) {
            return redirect()->to(site_url('packages/' . $packageId))
                ->with('error', 'The target rank for this promotion could not be determined; no promotion order was recorded.');
        }

        $user        = $this->currentUser();
        $actorUserId = $user !== null ? (int) $user->id : 0;

        $db = db_connect();
        $db->transStart();

        try {
            service('packageWorkflowService')->transition($packageId, 'promotion_order_recorded', $actorUserId);
        } catch (RuntimeException $exception) {
            $db->transComplete();

            return redirect()->to(site_url('packages/' . $packageId))->with('error', $exception->getMessage());
        }

        $orderNumber = $this->promotionOrderModel->nextOrderNumber();

        $this->promotionOrderModel->insert([
            'promotion_package_id' => $packageId,
            'reservist_id'         => $package['reservist_id'],
            'order_number'         => $orderNumber,
            'order_date'           => $orderDate,
            'effective_date'       => $effectiveDate,
            'new_rank_id'          => $newRankId,
            'recorded_by'          => $actorUserId ?: null,
        ]);

        // skipValidation: without it, ReservistModel's `not_future_date`
        // rule on `date_of_last_promotion` rejects an effective date that
        // is today-or-later relative to the server clock (a Records
        // Officer legitimately may record an order effective tomorrow),
        // and update() then returns false SILENTLY (no exception) --
        // leaving the reservist's rank stale even though the order and
        // rank-history rows above already succeeded. See
        // tests/unit/ReservistModelPartialUpdateTest.php for the
        // reproduction. This is a narrow, system-computed update (a rank
        // id we just resolved, a date the caller supplied for this exact
        // purpose) -- not the same trust boundary as a user editing their
        // own profile dates, so skipping validation here is safe.
        $reservistUpdated = $this->reservistModel->skipValidation(true)->update($package['reservist_id'], [
            'current_rank_id'        => $newRankId,
            'date_of_last_promotion' => $effectiveDate,
        ]);

        if (! $reservistUpdated) {
            $db->transRollback();

            return redirect()->to(site_url('packages/' . $packageId))
                ->with('error', 'The reservist record could not be updated with the new rank; the promotion order was not recorded.');
        }

        $this->rankHistoryModel->insert([
            'reservist_id'    => $package['reservist_id'],
            'rank_id'         => $newRankId,
            'effective_date'  => $effectiveDate,
            'order_reference' => $orderNumber,
            'created_by'      => $actorUserId ?: null,
        ]);

        $result = service('packageWorkflowService')->transition($packageId, 'closed', $actorUserId);

        $db->transComplete();

        service('auditLogService')->record(
            $actorUserId ?: null,
            'promotion_order_recorded',
            'reservist',
            (int) $package['reservist_id'],
            [],
            ['order_number' => $orderNumber, 'new_rank_id' => $newRankId, 'effective_date' => $effectiveDate]
        );

        return redirect()->to(site_url('packages/' . $packageId))
            ->with('success', 'Promotion order recorded; package is now "' . ucwords(str_replace('_', ' ', $result['status'])) . '".');
    }
}
