<?php

declare(strict_types=1);

namespace App\Controllers\Records;

use App\Controllers\BaseController;
use App\Models\ExternalForwardingRecordModel;
use App\Models\PromotionPackageModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Records the Records Officer forwarding an RCDG-endorsed package to
 * its external approving authority (Group Commander or RESCOM / PA
 * Headquarters -- outside the system, design doc §1). One user action
 * produces one `external_forwarding_records` row and advances the
 * package through both `forwarded_to_external_authority` and straight
 * to `awaiting_external_decision`: the intermediate status exists in
 * the state machine for history/audit completeness, but forwarding is
 * a single real-world action with nothing further for the user to do
 * once the reference number is recorded.
 */
class ForwardingController extends BaseController
{
    private PromotionPackageModel $promotionPackageModel;
    private ExternalForwardingRecordModel $externalForwardingRecordModel;

    public function __construct()
    {
        $this->promotionPackageModel         = new PromotionPackageModel();
        $this->externalForwardingRecordModel = new ExternalForwardingRecordModel();
    }

    public function forward(?int $packageId = null): RedirectResponse
    {
        if ($packageId === null) {
            throw new RuntimeException('A package id is required.');
        }

        $package = $this->promotionPackageModel->find($packageId);

        if ($package === null) {
            throw new RuntimeException("Promotion package #{$packageId} was not found.");
        }

        $authorityName   = trim((string) $this->request->getPost('external_authority_name'));
        $referenceNumber = trim((string) $this->request->getPost('forwarding_reference_number'));
        $remarks         = trim((string) $this->request->getPost('remarks')) ?: null;

        if ($authorityName === '' || $referenceNumber === '') {
            return redirect()->to(site_url('packages/' . $packageId))
                ->with('error', 'The approving authority name and a forwarding reference number are required.');
        }

        $user            = $this->currentUser();
        $actorUserId     = $user !== null ? (int) $user->id : 0;
        $workflowService = service('packageWorkflowService');

        try {
            $workflowService->transition($packageId, 'forwarded_to_external_authority', $actorUserId, $remarks);
            $result = $workflowService->transition($packageId, 'awaiting_external_decision', $actorUserId, $remarks);
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('packages/' . $packageId))->with('error', $exception->getMessage());
        }

        $this->externalForwardingRecordModel->insert([
            'promotion_package_id'        => $packageId,
            'forwarded_by'                => $actorUserId ?: null,
            'external_authority_name'     => $authorityName,
            'forwarding_reference_number' => $referenceNumber,
            'forwarded_date'              => date('Y-m-d'),
            'remarks'                     => $remarks,
        ]);

        return redirect()->to(site_url('packages/' . $packageId))
            ->with('success', 'Package forwarded to ' . $authorityName . '; status is now "' . ucwords(str_replace('_', ' ', $result['status'])) . '".');
    }
}
