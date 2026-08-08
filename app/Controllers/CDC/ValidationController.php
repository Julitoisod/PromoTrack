<?php

declare(strict_types=1);

namespace App\Controllers\CDC;

use App\Controllers\BaseController;
use App\Models\PackageReviewModel;
use App\Models\PromotionPackageModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * The 1502 CDC's documentary-completeness validation stage: moves a
 * package from `ready_for_cdc_validation` through `under_cdc_validation`
 * to either `cdc_validated` (forwarded on to RCDG) or `returned_to_unit`
 * (sent back with remarks). Each action maps 1:1 to a
 * `WorkflowStateMachine` edge; `PackageWorkflowService::transition()`
 * enforces reachability, the remarks requirement, and records the
 * status-history row.
 */
class ValidationController extends BaseController
{
    private PromotionPackageModel $promotionPackageModel;
    private PackageReviewModel $packageReviewModel;

    public function __construct()
    {
        $this->promotionPackageModel = new PromotionPackageModel();
        $this->packageReviewModel    = new PackageReviewModel();
    }

    /**
     * Begins CDC review: ready_for_cdc_validation -> under_cdc_validation.
     */
    public function review(?int $packageId = null): RedirectResponse
    {
        return $this->doTransition($packageId, 'under_cdc_validation');
    }

    /**
     * Validates the package: under_cdc_validation -> cdc_validated.
     */
    public function validatePackage(?int $packageId = null): RedirectResponse
    {
        $result = $this->doTransition($packageId, 'cdc_validated');

        if ($packageId !== null) {
            $this->recordReview($packageId, 'validated', null);
        }

        return $result;
    }

    /**
     * Returns the package to the unit with remarks:
     * under_cdc_validation -> returned_to_unit.
     */
    public function returnToUnit(?int $packageId = null): RedirectResponse
    {
        $remarks = trim((string) $this->request->getPost('remarks'));

        if ($remarks === '') {
            return redirect()->to(site_url('packages/' . $packageId))
                ->with('error', 'Remarks are required to return this package.');
        }

        $result = $this->doTransition($packageId, 'returned_to_unit', $remarks);

        if ($packageId !== null) {
            $this->recordReview($packageId, 'returned', $remarks);
            $this->notifyPackageCreator($packageId, 'Package returned by 1502 CDC', $remarks);
        }

        return $result;
    }

    /**
     * Forwards a validated package to the RCDG:
     * cdc_validated -> submitted_to_rcdg.
     */
    public function submitToRcdg(?int $packageId = null): RedirectResponse
    {
        $result = $this->doTransition($packageId, 'submitted_to_rcdg');

        if ($packageId !== null) {
            $this->notifyRcdgQueue($packageId);
        }

        return $result;
    }

    /**
     * Resubmits an RCDG-returned package: returned_by_rcdg ->
     * resubmitted_to_rcdg.
     */
    public function resubmitToRcdg(?int $packageId = null): RedirectResponse
    {
        $result = $this->doTransition($packageId, 'resubmitted_to_rcdg');

        if ($packageId !== null) {
            $this->notifyRcdgQueue($packageId);
        }

        return $result;
    }

    /**
     * Notifies every rcdg_reviewer user that a package has arrived in
     * their queue (Table 1, Workflow Routing - Level 1 functional
     * requirement).
     */
    private function notifyRcdgQueue(int $packageId): void
    {
        $package = $this->promotionPackageModel->find($packageId);

        if ($package === null) {
            return;
        }

        service('notificationService')->notifyGroup(
            'rcdg_reviewer',
            'package_arrived',
            'Package ' . $package['package_number'] . ' is ready for RCDG review',
            null,
            'promotion_package',
            $packageId
        );
    }

    private function notifyPackageCreator(int $packageId, string $title, ?string $body): void
    {
        $package = $this->promotionPackageModel->find($packageId);

        if ($package === null || $package['created_by'] === null) {
            return;
        }

        service('notificationService')->notify(
            (int) $package['created_by'],
            'package_returned',
            $title,
            $body,
            'promotion_package',
            $packageId
        );
    }

    private function recordReview(int $packageId, string $decision, ?string $remarks): void
    {
        $user = $this->currentUser();

        $this->packageReviewModel->insert([
            'promotion_package_id' => $packageId,
            'reviewer_id'          => $user !== null ? (int) $user->id : null,
            'review_stage'         => 'cdc',
            'decision'             => $decision,
            'remarks'              => $remarks,
        ]);
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
