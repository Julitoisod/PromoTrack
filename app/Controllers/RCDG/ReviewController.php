<?php

declare(strict_types=1);

namespace App\Controllers\RCDG;

use App\Controllers\BaseController;
use App\Models\EndorsementModel;
use App\Models\PackageReviewModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionPackageModel;
use App\Models\PromotionRuleModel;
use App\Models\RankModel;
use App\Models\ReservistModel;
use App\Services\PackageTargetRankResolver;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * The 15th RCDG's intermediate review and endorsement stage: moves a
 * package from `submitted_to_rcdg` (or `resubmitted_to_rcdg`) through
 * `under_rcdg_review` to either `rcdg_endorsed` or `returned_by_rcdg`.
 *
 * Endorsing also applies the AFP authority routing rule (Gate 3, design
 * doc §4): Pfc-to-Sgt packages route to the Group Commander queue,
 * SSg-to-MSg packages route to RESCOM / PA Headquarters. The exact rank
 * boundary of that rule REQUIRES OFFICIAL AFP/PHILIPPINE ARMY
 * VALIDATION; `determineRoutingLevel()` below documents the mapping
 * this codebase assumes.
 */
class ReviewController extends BaseController
{
    /** Enlisted rank codes routed to the Group Commander (Pfc-to-Sgt). */
    private const GROUP_COMMANDER_RANK_CODES = ['PFC', 'CPL', 'SGT'];

    private PromotionPackageModel $promotionPackageModel;
    private PackageReviewModel $packageReviewModel;
    private EndorsementModel $endorsementModel;
    private RankModel $rankModel;
    private PackageTargetRankResolver $targetRankResolver;

    public function __construct()
    {
        $this->promotionPackageModel = new PromotionPackageModel();
        $this->packageReviewModel    = new PackageReviewModel();
        $this->endorsementModel      = new EndorsementModel();
        $this->rankModel             = new RankModel();
        $this->targetRankResolver    = new PackageTargetRankResolver(
            new PromotionAssessmentModel(),
            new ReservistModel(),
            new PromotionRuleModel(),
        );
    }

    /**
     * Begins RCDG review: submitted_to_rcdg or resubmitted_to_rcdg ->
     * under_rcdg_review.
     */
    public function review(?int $packageId = null): RedirectResponse
    {
        return $this->doTransition($packageId, 'under_rcdg_review');
    }

    /**
     * Endorses the package: under_rcdg_review -> rcdg_endorsed. Records
     * the endorsement (reference number + date) and applies the
     * grade-range routing rule to `current_routing_level`.
     */
    public function endorse(?int $packageId = null): RedirectResponse
    {
        if ($packageId === null) {
            throw new RuntimeException('A package id is required.');
        }

        $package = $this->promotionPackageModel->find($packageId);

        if ($package === null) {
            throw new RuntimeException("Promotion package #{$packageId} was not found.");
        }

        $routingLevel = $this->determineRoutingLevel($package);
        $remarks      = trim((string) $this->request->getPost('remarks')) ?: null;
        $user         = $this->currentUser();

        try {
            $result = service('packageWorkflowService')->transition(
                $packageId,
                'rcdg_endorsed',
                $user !== null ? (int) $user->id : 0,
                $remarks
            );
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('packages/' . $packageId))->with('error', $exception->getMessage());
        }

        $this->promotionPackageModel->update($packageId, ['current_routing_level' => $routingLevel]);

        $this->endorsementModel->insert([
            'promotion_package_id'         => $packageId,
            'endorsed_by'                  => $user !== null ? (int) $user->id : null,
            'endorsement_reference_number' => $this->nextEndorsementReferenceNumber(),
            'endorsement_date'             => date('Y-m-d'),
            'remarks'                      => $remarks,
        ]);

        $this->packageReviewModel->insert([
            'promotion_package_id' => $packageId,
            'reviewer_id'          => $user !== null ? (int) $user->id : null,
            'review_stage'         => 'rcdg',
            'decision'             => 'endorsed',
            'remarks'              => $remarks,
        ]);

        service('notificationService')->notifyGroup(
            'records_officer',
            'package_endorsed',
            'Package ' . $package['package_number'] . ' endorsed and ready for external forwarding',
            null,
            'promotion_package',
            $packageId
        );

        return redirect()->to(site_url('packages/' . $packageId))
            ->with('success', 'Package endorsed and routed to ' . str_replace('_', ' ', $routingLevel) . '.');
    }

    /**
     * Returns the package to the 1502 CDC with remarks:
     * under_rcdg_review -> returned_by_rcdg.
     */
    public function returnToUnit(?int $packageId = null): RedirectResponse
    {
        $remarks = trim((string) $this->request->getPost('remarks'));

        if ($remarks === '') {
            return redirect()->to(site_url('packages/' . $packageId))
                ->with('error', 'Remarks are required to return this package.');
        }

        $result = $this->doTransition($packageId, 'returned_by_rcdg', $remarks);

        if ($packageId !== null) {
            $user = $this->currentUser();

            $this->packageReviewModel->insert([
                'promotion_package_id' => $packageId,
                'reviewer_id'          => $user !== null ? (int) $user->id : null,
                'review_stage'         => 'rcdg',
                'decision'             => 'returned',
                'remarks'              => $remarks,
            ]);

            service('notificationService')->notifyGroup(
                'cdc_administrator',
                'package_returned',
                'Package returned by 15th RCDG for correction',
                $remarks,
                'promotion_package',
                $packageId
            );
        }

        return $result;
    }

    /**
     * Determines whether $package's target rank falls in the
     * Pfc-to-Sgt (Group Commander) or SSg-to-MSg (RESCOM/PA HQ) bucket.
     */
    private function determineRoutingLevel(array $package): string
    {
        $toRankId = $this->targetRankResolver->resolve($package);

        if ($toRankId === null) {
            return 'Group_Cdr';
        }

        $rank = $this->rankModel->find($toRankId);

        return $rank !== null && in_array($rank['rank_code'], self::GROUP_COMMANDER_RANK_CODES, true)
            ? 'Group_Cdr'
            : 'RESCOM_PA_HQ';
    }

    private function nextEndorsementReferenceNumber(): string
    {
        $year  = date('Y');
        $count = $this->endorsementModel->like('endorsement_reference_number', "END-{$year}-", 'after')->countAllResults();

        return "END-{$year}-" . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    private function doTransition(?int $packageId, string $toStatus, ?string $remarks = null): RedirectResponse
    {
        if ($packageId === null) {
            throw new RuntimeException('A package id is required.');
        }

        $user = $this->currentUser();

        try {
            $result = service('packageWorkflowService')->transition(
                $packageId,
                $toStatus,
                $user !== null ? (int) $user->id : 0,
                $remarks
            );
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('packages/' . $packageId))->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('packages/' . $packageId))
            ->with('success', 'Package status updated to "' . ucwords(str_replace('_', ' ', $result['status'])) . '".');
    }
}
