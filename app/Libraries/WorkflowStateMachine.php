<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Encodes the promotion-package workflow transition table (design doc
 * §4) as pure data: which `to` statuses are reachable from a given
 * `from` status, the permission required to make that specific
 * transition, and whether it requires non-empty remarks (the "return
 * with remarks" gates at the CDC and RCDG stages).
 *
 * Pure and DB-free by design, mirroring `DateDurationCalculator` --
 * `PackageWorkflowService` is the only caller allowed to actually
 * change `promotion_packages.status`, and it consults this class first.
 *
 * The `archived` transition (closed -> archived) is a system_administrator
 * housekeeping action with no dedicated permission in `AuthGroups.php`;
 * it is included here for completeness of the design doc's table but,
 * being out of scope for this pass, has no controller action wired to it.
 */
final class WorkflowStateMachine
{
    /**
     * @var array<string, array<string, array{permission: string, requires_remarks: bool}>>
     */
    private const TRANSITIONS = [
        'draft' => [
            'requirements_incomplete'  => ['permission' => 'packages.create', 'requires_remarks' => false],
            'ready_for_cdc_validation' => ['permission' => 'packages.create', 'requires_remarks' => false],
        ],
        'requirements_incomplete' => [
            'ready_for_cdc_validation' => ['permission' => 'packages.create', 'requires_remarks' => false],
        ],
        'ready_for_cdc_validation' => [
            'under_cdc_validation' => ['permission' => 'packages.validate_at_cdc', 'requires_remarks' => false],
        ],
        'under_cdc_validation' => [
            'returned_to_unit' => ['permission' => 'packages.return', 'requires_remarks' => true],
            'cdc_validated'    => ['permission' => 'packages.validate_at_cdc', 'requires_remarks' => false],
        ],
        'returned_to_unit' => [
            'ready_for_cdc_validation' => ['permission' => 'packages.submit_to_cdc', 'requires_remarks' => false],
        ],
        'cdc_validated' => [
            'submitted_to_rcdg' => ['permission' => 'packages.submit_to_rcdg', 'requires_remarks' => false],
        ],
        'submitted_to_rcdg' => [
            'under_rcdg_review' => ['permission' => 'packages.review_at_rcdg', 'requires_remarks' => false],
        ],
        'under_rcdg_review' => [
            'returned_by_rcdg' => ['permission' => 'packages.return', 'requires_remarks' => true],
            'rcdg_endorsed'    => ['permission' => 'packages.endorse', 'requires_remarks' => false],
        ],
        'returned_by_rcdg' => [
            'resubmitted_to_rcdg' => ['permission' => 'packages.submit_to_rcdg', 'requires_remarks' => false],
        ],
        'resubmitted_to_rcdg' => [
            'under_rcdg_review' => ['permission' => 'packages.review_at_rcdg', 'requires_remarks' => false],
        ],
        'rcdg_endorsed' => [
            'forwarded_to_external_authority' => ['permission' => 'forwarding.record', 'requires_remarks' => false],
        ],
        'forwarded_to_external_authority' => [
            'awaiting_external_decision' => ['permission' => 'forwarding.record', 'requires_remarks' => false],
        ],
        'awaiting_external_decision' => [
            'approved'    => ['permission' => 'external_decisions.record', 'requires_remarks' => false],
            'disapproved' => ['permission' => 'external_decisions.record', 'requires_remarks' => false],
            'deferred'    => ['permission' => 'external_decisions.record', 'requires_remarks' => false],
        ],
        'deferred' => [
            'awaiting_external_decision' => ['permission' => 'external_decisions.record', 'requires_remarks' => false],
        ],
        'approved' => [
            'promotion_order_recorded' => ['permission' => 'promotion_orders.record', 'requires_remarks' => false],
        ],
        'disapproved' => [
            'closed' => ['permission' => 'external_decisions.record', 'requires_remarks' => false],
        ],
        'promotion_order_recorded' => [
            'closed' => ['permission' => 'promotion_orders.record', 'requires_remarks' => false],
        ],
        'closed' => [
            'archived' => ['permission' => 'settings.manage', 'requires_remarks' => false],
        ],
    ];

    /**
     * Statuses considered terminal for the "one open package per
     * reservist" rule (`promotion_packages.open_marker`).
     */
    private const TERMINAL_STATUSES = ['disapproved', 'closed', 'archived'];

    public function canTransition(string $from, string $to): bool
    {
        return isset(self::TRANSITIONS[$from][$to]);
    }

    /**
     * @return list<string>
     */
    public function allowedNextStatuses(string $from): array
    {
        return array_keys(self::TRANSITIONS[$from] ?? []);
    }

    public function permissionFor(string $from, string $to): ?string
    {
        return self::TRANSITIONS[$from][$to]['permission'] ?? null;
    }

    public function requiresRemarks(string $from, string $to): bool
    {
        return self::TRANSITIONS[$from][$to]['requires_remarks'] ?? false;
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    /**
     * @return list<string> Every status name known to the machine.
     */
    public function allStatuses(): array
    {
        $statuses = [];

        foreach (self::TRANSITIONS as $from => $targets) {
            $statuses[$from] = true;

            foreach (array_keys($targets) as $to) {
                $statuses[$to] = true;
            }
        }

        return array_keys($statuses);
    }
}
