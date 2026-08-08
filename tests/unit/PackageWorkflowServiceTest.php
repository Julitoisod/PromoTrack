<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\WorkflowStateMachine;
use App\Models\DocumentRequirementModel;
use App\Models\DocumentRequirementTemplateModel;
use App\Models\PackageDocumentModel;
use App\Models\PackageStatusHistoryModel;
use App\Models\PromotionPackageModel;
use App\Services\DocumentValidationService;
use App\Services\PackageWorkflowService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * @internal
 */
final class PackageWorkflowServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    private PackageWorkflowService $workflowService;
    private DocumentValidationService $documentValidationService;
    private PackageDocumentModel $packageDocumentModel;
    private PromotionPackageModel $promotionPackageModel;

    private int $reservistId;
    private int $unitId;
    private int $requirementAId;
    private int $requirementBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionPackageModel = new PromotionPackageModel();
        $this->packageDocumentModel  = new PackageDocumentModel();

        $this->documentValidationService = new DocumentValidationService(
            new DocumentRequirementTemplateModel(),
            new DocumentRequirementModel(),
            $this->packageDocumentModel,
        );

        $this->workflowService = new PackageWorkflowService(
            $this->promotionPackageModel,
            new PackageStatusHistoryModel(),
            $this->documentValidationService,
            new WorkflowStateMachine(),
        );

        $this->unitId = $this->insertRow('organizational_units', [
            'unit_code' => 'TESTU',
            'unit_name' => 'Test Unit',
            'unit_type' => 'battalion',
            'is_active' => 1,
        ]);

        $rankId = $this->insertRow('ranks', [
            'rank_code'  => 'CPL',
            'rank_name'  => 'Corporal',
            'sort_order' => 1,
            'is_active'  => 1,
        ]);

        $this->reservistId = $this->insertRow('reservists', [
            'reservist_number'       => 'RSV-200',
            'first_name'             => 'Juan',
            'last_name'              => 'Dela Cruz',
            'date_of_enlistment'     => '2015-01-01',
            'current_rank_id'        => $rankId,
            'organizational_unit_id' => $this->unitId,
            'status'                 => 'active',
        ]);

        $templateId = $this->insertRow('document_requirement_templates', [
            'promotion_rule_id' => null,
            'template_name'     => 'Test Checklist',
            'version'           => 1,
            'is_active'         => 1,
        ]);

        $this->requirementAId = $this->insertRow('document_requirements', [
            'document_requirement_template_id' => $templateId,
            'document_code'                     => 'A',
            'document_label'                    => 'Test Item A',
            'is_conditional'                    => 0,
            'display_order'                     => 1,
        ]);

        $this->requirementBId = $this->insertRow('document_requirements', [
            'document_requirement_template_id' => $templateId,
            'document_code'                     => 'B',
            'document_label'                    => 'Test Item B',
            'is_conditional'                    => 0,
            'display_order'                     => 2,
        ]);
    }

    private function insertRow(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    public function testCreateInstantiatesTheDocumentChecklist(): void
    {
        $package = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);

        $this->assertSame('draft', $package['status']);
        $this->assertSame(1, (int) $package['open_marker']);

        $checklist = $this->packageDocumentModel->checklistFor((int) $package['id']);
        $this->assertCount(2, $checklist);
        $this->assertSame('pending', $checklist[0]['status']);
    }

    public function testCreateBlocksASecondOpenPackageForTheSameReservist(): void
    {
        $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);

        $this->expectException(RuntimeException::class);
        $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);
    }

    public function testSubmitFromUnitRoutesToRequirementsIncompleteWhenDocumentsAreMissing(): void
    {
        $package = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);

        $result = $this->workflowService->submitFromUnit((int) $package['id'], 1);

        $this->assertSame('requirements_incomplete', $result['status']);
    }

    public function testSubmitFromUnitRoutesToReadyForCdcValidationWhenDocumentsAreComplete(): void
    {
        $package = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);

        $this->markAllDocumentsUploaded((int) $package['id']);

        $result = $this->workflowService->submitFromUnit((int) $package['id'], 1);

        $this->assertSame('ready_for_cdc_validation', $result['status']);
        $this->assertSame(100.0, (float) $result['completeness_percentage']);
    }

    public function testTransitionRejectsAnUnreachableTargetStatus(): void
    {
        $package = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);

        $this->expectException(RuntimeException::class);
        $this->workflowService->transition((int) $package['id'], 'under_rcdg_review', 1);
    }

    public function testTransitionRequiresRemarksWhenReturningAPackage(): void
    {
        $package = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);
        $this->markAllDocumentsUploaded((int) $package['id']);
        $this->workflowService->submitFromUnit((int) $package['id'], 1);
        $this->workflowService->transition((int) $package['id'], 'under_cdc_validation', 2);

        $this->expectException(RuntimeException::class);
        $this->workflowService->transition((int) $package['id'], 'returned_to_unit', 2);
    }

    public function testOpenMarkerSurvivesANonTerminalReturnTransition(): void
    {
        $package = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);
        $this->markAllDocumentsUploaded((int) $package['id']);
        $this->workflowService->submitFromUnit((int) $package['id'], 1);
        $this->workflowService->transition((int) $package['id'], 'under_cdc_validation', 2);
        $result = $this->workflowService->transition((int) $package['id'], 'returned_to_unit', 2, 'Missing signature.');

        $this->assertSame('returned_to_unit', $result['status']);
        $this->assertSame(1, (int) $result['open_marker']);
    }

    public function testOpenMarkerClearsOnceThePackageReachesATerminalStatus(): void
    {
        $package   = $this->workflowService->create($this->reservistId, $this->unitId, null, null, 1);
        $packageId = (int) $package['id'];
        $this->markAllDocumentsUploaded($packageId);

        $this->workflowService->submitFromUnit($packageId, 1);
        $this->workflowService->transition($packageId, 'under_cdc_validation', 2);
        $this->workflowService->transition($packageId, 'cdc_validated', 2);
        $this->workflowService->transition($packageId, 'submitted_to_rcdg', 2);
        $this->workflowService->transition($packageId, 'under_rcdg_review', 3);
        $this->workflowService->transition($packageId, 'rcdg_endorsed', 3);
        $this->workflowService->transition($packageId, 'forwarded_to_external_authority', 4);
        $this->workflowService->transition($packageId, 'awaiting_external_decision', 4);
        $this->workflowService->transition($packageId, 'approved', 4);
        $this->workflowService->transition($packageId, 'promotion_order_recorded', 4);
        $result = $this->workflowService->transition($packageId, 'closed', 4);

        $this->assertSame('closed', $result['status']);
        $this->assertNull($result['open_marker']);

        // A closed package no longer counts as "open", so the reservist
        // can now have a new package created for them.
        $this->assertFalse($this->promotionPackageModel->hasOpenPackage($this->reservistId));
    }

    private function markAllDocumentsUploaded(int $packageId): void
    {
        $this->db->table('package_documents')
            ->where('promotion_package_id', $packageId)
            ->update(['status' => 'uploaded']);
    }
}
