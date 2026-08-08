<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentRequirementModel;
use App\Models\DocumentRequirementTemplateModel;
use App\Models\PackageDocumentModel;

/**
 * Builds and evaluates the thirteen-item promotion document checklist
 * (Items A-M, Table 4 of the thesis) for a promotion package.
 *
 * A checklist item is considered "satisfied" -- and therefore no longer
 * blocking submission -- when its status is `uploaded`, `verified`, or
 * `waived`. `waived` is also how this system implements Table 4's
 * conditional items: Item K (PRC/CSC) is waivable for non-licensed
 * reservists, and Item I (Awards) "allows submission with commander
 * override" is implemented as a waiver an authorized reviewer records,
 * rather than a separate override mechanism -- one status value, one
 * code path, per YAGNI.
 */
class DocumentValidationService
{
    private const SATISFIED_STATUSES = ['uploaded', 'verified', 'waived'];

    public function __construct(
        private readonly DocumentRequirementTemplateModel $templateModel,
        private readonly DocumentRequirementModel $requirementModel,
        private readonly PackageDocumentModel $packageDocumentModel,
    ) {
    }

    /**
     * Instantiates one `package_documents` row per item in the active
     * template for $promotionRuleId (or the rule-agnostic template when
     * none is rule-specific) against $promotionPackageId. Idempotent:
     * does nothing if the package already has checklist rows.
     */
    public function instantiateChecklist(int $promotionPackageId, ?int $promotionRuleId): void
    {
        if ($this->packageDocumentModel->where('promotion_package_id', $promotionPackageId)->countAllResults() > 0) {
            return;
        }

        $template = $this->templateModel->activeTemplateFor($promotionRuleId);

        if ($template === null) {
            return;
        }

        $requirements = $this->requirementModel->forTemplate((int) $template['id']);

        foreach ($requirements as $requirement) {
            $this->packageDocumentModel->insert([
                'promotion_package_id'    => $promotionPackageId,
                'document_requirement_id' => $requirement['id'],
                'status'                  => 'pending',
            ]);
        }
    }

    /**
     * True when every checklist item for $promotionPackageId is
     * uploaded, verified, or waived -- i.e. nothing remains `pending`
     * or `rejected`. This is Gate 1 (design doc §4): the submission
     * block.
     */
    public function isComplete(int $promotionPackageId): bool
    {
        $checklist = $this->packageDocumentModel->checklistFor($promotionPackageId);

        if ($checklist === []) {
            return false;
        }

        foreach ($checklist as $item) {
            if (! in_array($item['status'], self::SATISFIED_STATUSES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Percentage (0-100) of checklist items currently satisfied, for
     * display on the package/dashboard views.
     */
    public function computeCompleteness(int $promotionPackageId): float
    {
        $checklist = $this->packageDocumentModel->checklistFor($promotionPackageId);

        if ($checklist === []) {
            return 0.0;
        }

        $satisfied = 0;

        foreach ($checklist as $item) {
            if (in_array($item['status'], self::SATISFIED_STATUSES, true)) {
                $satisfied++;
            }
        }

        return round(($satisfied / count($checklist)) * 100, 2);
    }

    /**
     * Returns the checklist items still blocking submission (`pending`
     * or `rejected`), for the "missing item" notice shown to the Unit
     * S1 Clerk.
     */
    public function missingItems(int $promotionPackageId): array
    {
        return array_values(array_filter(
            $this->packageDocumentModel->checklistFor($promotionPackageId),
            static fn (array $item): bool => ! in_array($item['status'], self::SATISFIED_STATUSES, true)
        ));
    }
}
