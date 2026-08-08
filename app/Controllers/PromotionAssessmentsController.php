<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PromotionAssessmentModel;
use App\Models\PromotionRuleModel;
use App\Models\ReservistModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Runs and records promotion-eligibility assessments for a reservist.
 *
 * Thin by design, matching `ReservistsController`: permission and
 * unit-ownership are enforced by route filters (see Config/Routes.php)
 * plus the same `belongsToUserUnit()` defense-in-depth check used
 * throughout the app; the actual eligibility logic lives entirely in
 * `App\Services\EligibilityService`.
 */
class PromotionAssessmentsController extends BaseController
{
    private ReservistModel $reservistModel;
    private PromotionRuleModel $promotionRuleModel;
    private PromotionAssessmentModel $promotionAssessmentModel;

    public function __construct()
    {
        $this->reservistModel           = new ReservistModel();
        $this->promotionRuleModel       = new PromotionRuleModel();
        $this->promotionAssessmentModel = new PromotionAssessmentModel();
    }

    /**
     * Computes and persists a new assessment for $reservistId. The
     * target rank is taken from the `to_rank_id` POST field when given;
     * otherwise it is auto-derived from the single active promotion
     * rule configured for the reservist's current rank.
     */
    public function store(?int $reservistId = null): RedirectResponse
    {
        $reservist = $this->findAccessibleReservistOrFail($reservistId);

        $toRankId = $this->request->getPost('to_rank_id')
            ? (int) $this->request->getPost('to_rank_id')
            : $this->deriveNextRankId($reservist->current_rank_id);

        if ($toRankId === null) {
            return redirect()->to(site_url('reservists/' . $reservist->id))
                ->with('error', 'No target rank was given, and no active promotion rule is configured for this reservist\'s current rank.');
        }

        $user = $this->currentUser();

        $result = service('eligibilityService')->assessAndSave(
            $reservist->id,
            $toRankId,
            $user !== null ? (int) $user->id : 0
        );

        $message = match ($result['promotion_status']) {
            'overdue'          => 'Assessment recorded: this reservist is OVERDUE for promotion.',
            'eligible'         => 'Assessment recorded: this reservist is ELIGIBLE for promotion.',
            'not_yet_eligible' => 'Assessment recorded: this reservist is not yet eligible for promotion.',
            default            => 'Assessment recorded. One or more criteria require manual review.',
        };

        return redirect()->to(site_url('reservists/' . $reservist->id))
            ->with($result['is_eligible'] ? 'success' : 'info', $message);
    }

    /**
     * Records CDC verification (countersignature) of a previously
     * computed assessment. Verification does not re-run the
     * computation -- it only timestamps that a human reviewed it.
     */
    public function verify(?int $assessmentId = null): RedirectResponse
    {
        if ($assessmentId === null) {
            throw new RuntimeException('An assessment id is required.');
        }

        $assessment = $this->promotionAssessmentModel->find($assessmentId);

        if ($assessment === null) {
            throw new RuntimeException("Assessment #{$assessmentId} was not found.");
        }

        $reservist = $this->findAccessibleReservistOrFail((int) $assessment['reservist_id']);

        $user = $this->currentUser();

        $this->promotionAssessmentModel->update($assessmentId, [
            'verified_by' => $user !== null ? (int) $user->id : null,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(site_url('reservists/' . $reservist->id))
            ->with('success', 'Assessment verified.');
    }

    private function deriveNextRankId(?int $currentRankId): ?int
    {
        if ($currentRankId === null) {
            return null;
        }

        $rule = $this->promotionRuleModel->findActiveRuleForFromRank($currentRankId);

        return $rule !== null ? (int) $rule['to_rank_id'] : null;
    }

    private function findAccessibleReservistOrFail(?int $id): \App\Entities\Reservist
    {
        if ($id === null) {
            throw new RuntimeException('A reservist id is required.');
        }

        $reservist = $this->reservistModel->find($id);

        if ($reservist === null) {
            throw new RuntimeException("Reservist #{$id} was not found.");
        }

        if (! $this->belongsToUserUnit($reservist->organizational_unit_id)) {
            throw new RuntimeException('You are not authorized to access this reservist record.');
        }

        return $reservist;
    }
}
