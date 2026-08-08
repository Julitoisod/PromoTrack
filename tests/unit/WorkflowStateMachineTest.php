<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\WorkflowStateMachine;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class WorkflowStateMachineTest extends CIUnitTestCase
{
    private WorkflowStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machine = new WorkflowStateMachine();
    }

    public function testAllowsTheDocumentedHappyPathTransitions(): void
    {
        $happyPath = [
            'draft'                           => 'ready_for_cdc_validation',
            'ready_for_cdc_validation'        => 'under_cdc_validation',
            'under_cdc_validation'            => 'cdc_validated',
            'cdc_validated'                   => 'submitted_to_rcdg',
            'submitted_to_rcdg'               => 'under_rcdg_review',
            'under_rcdg_review'               => 'rcdg_endorsed',
            'rcdg_endorsed'                   => 'forwarded_to_external_authority',
            'forwarded_to_external_authority' => 'awaiting_external_decision',
            'awaiting_external_decision'      => 'approved',
            'approved'                        => 'promotion_order_recorded',
            'promotion_order_recorded'        => 'closed',
        ];

        foreach ($happyPath as $from => $to) {
            $this->assertTrue(
                $this->machine->canTransition($from, $to),
                "Expected {$from} -> {$to} to be allowed"
            );
        }
    }

    public function testRejectsSkippingAStage(): void
    {
        $this->assertFalse($this->machine->canTransition('draft', 'under_rcdg_review'));
        $this->assertFalse($this->machine->canTransition('submitted_to_rcdg', 'rcdg_endorsed'));
    }

    public function testRejectsMovingBackwardsOutsideTheDefinedReturnPaths(): void
    {
        $this->assertFalse($this->machine->canTransition('rcdg_endorsed', 'draft'));
        $this->assertFalse($this->machine->canTransition('closed', 'promotion_order_recorded'));
    }

    public function testReturnTransitionsRequireRemarks(): void
    {
        $this->assertTrue($this->machine->requiresRemarks('under_cdc_validation', 'returned_to_unit'));
        $this->assertTrue($this->machine->requiresRemarks('under_rcdg_review', 'returned_by_rcdg'));
        $this->assertFalse($this->machine->requiresRemarks('under_cdc_validation', 'cdc_validated'));
    }

    public function testEachTransitionMapsToItsDocumentedPermission(): void
    {
        $this->assertSame('packages.validate_at_cdc', $this->machine->permissionFor('ready_for_cdc_validation', 'under_cdc_validation'));
        $this->assertSame('packages.endorse', $this->machine->permissionFor('under_rcdg_review', 'rcdg_endorsed'));
        $this->assertSame('promotion_orders.record', $this->machine->permissionFor('approved', 'promotion_order_recorded'));
        $this->assertNull($this->machine->permissionFor('closed', 'draft'));
    }

    public function testTerminalStatusesMatchThePackagesOpenMarkerRule(): void
    {
        $this->assertTrue($this->machine->isTerminal('closed'));
        $this->assertTrue($this->machine->isTerminal('archived'));
        $this->assertTrue($this->machine->isTerminal('disapproved'));
        $this->assertFalse($this->machine->isTerminal('under_rcdg_review'));
    }

    public function testAwaitingExternalDecisionAllowsAllThreeOutcomes(): void
    {
        $this->assertSame(
            ['approved', 'disapproved', 'deferred'],
            $this->machine->allowedNextStatuses('awaiting_external_decision')
        );
    }
}
