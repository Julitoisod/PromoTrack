<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WorkflowStateMachine;
use App\Models\PackageStatusHistoryModel;
use App\Models\PromotionPackageModel;
use RuntimeException;

/**
 * The only entry point allowed to change `promotion_packages.status`
 * (design doc §11). Wraps `WorkflowStateMachine::canTransition()`,
 * `DocumentValidationService`'s completeness gate, a DB transaction,
 * and a `package_status_histories` insert into one call.
 *
 * Permission checks are deliberately NOT performed here -- consistent
 * with `EligibilityService`, authorization is the route filter's and
 * controller's job (see `BaseController::authorize()`); each controller
 * action maps to one hardcoded transition, so the permission filter on
 * that route is what actually restricts who may call it.
 */
class PackageWorkflowService
{
    public function __construct(
        private readonly PromotionPackageModel $promotionPackageModel,
        private readonly PackageStatusHistoryModel $packageStatusHistoryModel,
        private readonly DocumentValidationService $documentValidationService,
        private readonly WorkflowStateMachine $stateMachine,
        private readonly ?AuditLogService $auditLogService = null,
    ) {
    }

    /**
     * Creates a new package in `draft` status for $reservistId and
     * instantiates its document checklist. Throws when the reservist
     * already has an open (non-terminal) package.
     */
    public function create(
        int $reservistId,
        int $organizationalUnitId,
        ?int $promotionAssessmentId,
        ?int $promotionRuleId,
        int $actorUserId
    ): array {
        if ($this->promotionPackageModel->hasOpenPackage($reservistId)) {
            throw new RuntimeException('This reservist already has an open promotion package.');
        }

        $db = db_connect();
        $db->transStart();

        $this->promotionPackageModel->insert([
            'reservist_id'            => $reservistId,
            'promotion_assessment_id' => $promotionAssessmentId,
            'organizational_unit_id'  => $organizationalUnitId,
            'package_number'          => $this->promotionPackageModel->nextPackageNumber(),
            'status'                  => 'draft',
            'current_routing_level'   => 'CDC',
            'open_marker'             => 1,
            'created_by'              => $actorUserId,
        ]);

        $packageId = $this->promotionPackageModel->getInsertID();

        $this->documentValidationService->instantiateChecklist($packageId, $promotionRuleId);

        $this->packageStatusHistoryModel->insert([
            'promotion_package_id' => $packageId,
            'from_status'          => null,
            'to_status'            => 'draft',
            'changed_by'           => $actorUserId,
            'remarks'              => null,
        ]);

        $db->transComplete();

        return $this->promotionPackageModel->find($packageId);
    }

    /**
     * Moves $packageId from its current status to $toStatus, enforcing
     * the state machine's reachability rule, its remarks requirement,
     * and (only when the destination is `ready_for_cdc_validation`) the
     * document-completeness gate.
     */
    public function transition(int $packageId, string $toStatus, int $actorUserId, ?string $remarks = null): array
    {
        $package = $this->promotionPackageModel->find($packageId);

        if ($package === null) {
            throw new RuntimeException("Promotion package #{$packageId} was not found.");
        }

        $fromStatus = $package['status'];

        if (! $this->stateMachine->canTransition($fromStatus, $toStatus)) {
            throw new RuntimeException("This package cannot move from \"{$fromStatus}\" to \"{$toStatus}\".");
        }

        if ($this->stateMachine->requiresRemarks($fromStatus, $toStatus) && trim((string) $remarks) === '') {
            throw new RuntimeException('Remarks are required to return this package.');
        }

        if ($toStatus === 'ready_for_cdc_validation' && ! $this->documentValidationService->isComplete($packageId)) {
            throw new RuntimeException('This package cannot proceed: one or more required documents are missing.');
        }

        $db = db_connect();
        $db->transStart();

        $updateData = [
            'status'                  => $toStatus,
            'completeness_percentage' => $this->documentValidationService->computeCompleteness($packageId),
        ];

        if ($this->stateMachine->isTerminal($toStatus)) {
            $updateData['open_marker'] = null;
        }

        $this->promotionPackageModel->update($packageId, $updateData);

        $this->packageStatusHistoryModel->insert([
            'promotion_package_id' => $packageId,
            'from_status'          => $fromStatus,
            'to_status'            => $toStatus,
            'changed_by'           => $actorUserId,
            'remarks'              => $remarks,
        ]);

        $db->transComplete();

        $this->auditLogService?->record(
            $actorUserId ?: null,
            'package_status_changed',
            'promotion_package',
            $packageId,
            ['status' => $fromStatus],
            ['status' => $toStatus]
        );

        return $this->promotionPackageModel->find($packageId);
    }

    /**
     * The Unit S1 Clerk's "submit" action: automatically routes to
     * `ready_for_cdc_validation` when the checklist is complete, or
     * `requirements_incomplete` otherwise, per design doc §4's
     * "completeness check result" note on the draft transition.
     */
    public function submitFromUnit(int $packageId, int $actorUserId): array
    {
        $target = $this->documentValidationService->isComplete($packageId)
            ? 'ready_for_cdc_validation'
            : 'requirements_incomplete';

        return $this->transition($packageId, $target, $actorUserId);
    }
}
