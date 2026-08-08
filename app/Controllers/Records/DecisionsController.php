<?php

declare(strict_types=1);

namespace App\Controllers\Records;

use App\Controllers\BaseController;
use App\Models\ExternalDecisionModel;
use App\Models\PromotionPackageModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Records a decision received FROM the external approving authority
 * (approved / disapproved / deferred). The system never renders or
 * simulates that decision itself -- it is entered here by the Records
 * Officer after being received outside the system (design doc §1).
 *
 * A `disapproved` decision has nowhere else to go per the state
 * machine (its only edge is `disapproved -> closed`), so this
 * controller advances it there automatically in the same action rather
 * than requiring a separate "close" click for a foregone conclusion.
 */
class DecisionsController extends BaseController
{
    private PromotionPackageModel $promotionPackageModel;
    private ExternalDecisionModel $externalDecisionModel;

    public function __construct()
    {
        $this->promotionPackageModel = new PromotionPackageModel();
        $this->externalDecisionModel = new ExternalDecisionModel();
    }

    /**
     * Records a decision for a package currently `awaiting_external_decision`
     * (or resuming from `deferred`): approved, disapproved, or deferred.
     */
    public function record(?int $packageId = null): RedirectResponse
    {
        if ($packageId === null) {
            throw new RuntimeException('A package id is required.');
        }

        $decision              = (string) $this->request->getPost('decision');
        $decidingAuthorityName = trim((string) $this->request->getPost('deciding_authority_name'));
        $decisionDate          = trim((string) $this->request->getPost('decision_date')) ?: date('Y-m-d');
        $referenceNumber       = trim((string) $this->request->getPost('decision_reference_number')) ?: null;
        $remarks               = trim((string) $this->request->getPost('remarks')) ?: null;

        if (! in_array($decision, ['approved', 'disapproved', 'deferred'], true) || $decidingAuthorityName === '') {
            return redirect()->to(site_url('packages/' . $packageId))
                ->with('error', 'A decision and the deciding authority name are required.');
        }

        $user            = $this->currentUser();
        $actorUserId     = $user !== null ? (int) $user->id : 0;
        $workflowService = service('packageWorkflowService');

        try {
            $result = $workflowService->transition($packageId, $decision, $actorUserId, $remarks);

            if ($decision === 'disapproved') {
                $result = $workflowService->transition($packageId, 'closed', $actorUserId, $remarks);
            }
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('packages/' . $packageId))->with('error', $exception->getMessage());
        }

        $this->externalDecisionModel->insert([
            'promotion_package_id'      => $packageId,
            'recorded_by'               => $actorUserId ?: null,
            'decision'                  => $decision,
            'decision_reference_number' => $referenceNumber,
            'decision_date'             => $decisionDate,
            'deciding_authority_name'   => $decidingAuthorityName,
            'remarks'                   => $remarks,
        ]);

        return redirect()->to(site_url('packages/' . $packageId))
            ->with('success', 'Decision recorded; status is now "' . ucwords(str_replace('_', ' ', $result['status'])) . '".');
    }

    /**
     * Resumes a `deferred` package back to `awaiting_external_decision`
     * once a decision is later received.
     */
    public function resume(?int $packageId = null): RedirectResponse
    {
        if ($packageId === null) {
            throw new RuntimeException('A package id is required.');
        }

        $user = $this->currentUser();

        try {
            $result = service('packageWorkflowService')->transition(
                $packageId,
                'awaiting_external_decision',
                $user !== null ? (int) $user->id : 0
            );
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('packages/' . $packageId))->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('packages/' . $packageId))
            ->with('success', 'Package status updated to "' . ucwords(str_replace('_', ' ', $result['status'])) . '".');
    }
}
