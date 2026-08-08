<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageDocumentModel;
use App\Models\PackageStatusHistoryModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionOrderModel;
use App\Models\PromotionPackageModel;
use App\Models\PromotionRuleModel;
use App\Models\ReservistModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Creates and displays promotion packages, and lets a Unit S1 Clerk
 * submit a package into the CDC review queue. The CDC/RCDG/external
 * stage actions live in their own controllers (Phase D) -- this
 * controller only covers package creation and the unit-level submit,
 * per the same thin-controller convention as `ReservistsController`.
 */
class PromotionPackagesController extends BaseController
{
    private PromotionPackageModel $promotionPackageModel;
    private ReservistModel $reservistModel;
    private PromotionRuleModel $promotionRuleModel;
    private PromotionAssessmentModel $promotionAssessmentModel;
    private PackageDocumentModel $packageDocumentModel;
    private PackageStatusHistoryModel $packageStatusHistoryModel;

    public function __construct()
    {
        $this->promotionPackageModel     = new PromotionPackageModel();
        $this->reservistModel            = new ReservistModel();
        $this->promotionRuleModel        = new PromotionRuleModel();
        $this->promotionAssessmentModel  = new PromotionAssessmentModel();
        $this->packageDocumentModel      = new PackageDocumentModel();
        $this->packageStatusHistoryModel = new PackageStatusHistoryModel();
    }

    /**
     * Lists packages visible to the current user: scoped to their own
     * unit for unit_s1_clerk, cross-unit for every other role (whose
     * job is precisely to see packages arriving from multiple units).
     */
    public function index(): string
    {
        $user = $this->currentUser();

        $unitScopedId = $user !== null && $user->inGroup('unit_s1_clerk')
            ? (int) $user->organizational_unit_id
            : null;

        $status = $this->request->getGet('status');

        return view('packages/index', [
            'title'       => 'Promotion Packages',
            'breadcrumbs' => [
                'Dashboard' => site_url('/'),
                'Packages'  => '',
            ],
            'packages'      => $this->promotionPackageModel->listForDisplay($unitScopedId, $status !== null ? [$status] : null),
            'currentStatus' => $status,
        ]);
    }

    /**
     * Creates a new promotion package for $reservistId. The target rank
     * and promotion rule are derived the same way
     * `PromotionAssessmentsController` derives them: from the single
     * active promotion rule configured for the reservist's current
     * rank. The most recent assessment against that target rank (if
     * any) is linked for traceability.
     */
    public function store(?int $reservistId = null): RedirectResponse
    {
        if ($reservistId === null) {
            throw new RuntimeException('A reservist id is required.');
        }

        $reservist = $this->reservistModel->find($reservistId);

        if ($reservist === null) {
            throw new RuntimeException("Reservist #{$reservistId} was not found.");
        }

        if (! $this->belongsToUserUnit($reservist->organizational_unit_id)) {
            throw new RuntimeException('You are not authorized to access this reservist record.');
        }

        if ($reservist->organizational_unit_id === null) {
            return redirect()->to(site_url('reservists/' . $reservist->id))
                ->with('error', 'This reservist has no organizational unit on file; a package cannot be created.');
        }

        $rule = $reservist->current_rank_id !== null
            ? $this->promotionRuleModel->findActiveRuleForFromRank((int) $reservist->current_rank_id)
            : null;

        $assessment = $rule !== null
            ? $this->promotionAssessmentModel->mostRecentFor($reservist->id, (int) $rule['to_rank_id'])
            : null;

        $user = $this->currentUser();

        try {
            $package = service('packageWorkflowService')->create(
                $reservist->id,
                (int) $reservist->organizational_unit_id,
                isset($assessment['id']) ? (int) $assessment['id'] : null,
                isset($rule['id']) ? (int) $rule['id'] : null,
                $user !== null ? (int) $user->id : 0
            );
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('reservists/' . $reservist->id))
                ->with('error', $exception->getMessage());
        }

        return redirect()->to(site_url('packages/' . $package['id']))
            ->with('success', 'Promotion package created. Upload the required documents to proceed.');
    }

    public function show(?int $packageId = null): string
    {
        $package = $this->findAccessiblePackageOrFail($packageId);

        $promotionOrder = (new PromotionOrderModel())
            ->where('promotion_package_id', $package['id'])
            ->first();

        return view('packages/show', [
            'title'       => 'Promotion Package ' . $package['package_number'],
            'breadcrumbs' => [
                'Dashboard' => site_url('/'),
                'Packages'  => site_url('packages'),
                $package['package_number'] => '',
            ],
            'package'        => $package,
            'checklist'      => $this->packageDocumentModel->checklistFor((int) $package['id']),
            'history'        => $this->packageStatusHistoryModel->forPackage((int) $package['id']),
            'promotionOrder' => $promotionOrder,
        ]);
    }

    /**
     * The Unit S1 Clerk's "submit" action: routes to
     * `ready_for_cdc_validation` or `requirements_incomplete`
     * automatically based on checklist completeness.
     */
    public function submit(?int $packageId = null): RedirectResponse
    {
        $package = $this->findAccessiblePackageOrFail($packageId);
        $user    = $this->currentUser();

        try {
            $updated = service('packageWorkflowService')->submitFromUnit(
                (int) $package['id'],
                $user !== null ? (int) $user->id : 0
            );
        } catch (RuntimeException $exception) {
            return redirect()->to(site_url('packages/' . $package['id']))
                ->with('error', $exception->getMessage());
        }

        $message = $updated['status'] === 'ready_for_cdc_validation'
            ? 'Package submitted to the 1502 CDC for validation.'
            : 'Package could not be fully submitted: some required documents are still missing.';

        return redirect()->to(site_url('packages/' . $package['id']))
            ->with($updated['status'] === 'ready_for_cdc_validation' ? 'success' : 'info', $message);
    }

    private function findAccessiblePackageOrFail(?int $id): array
    {
        if ($id === null) {
            throw new RuntimeException('A package id is required.');
        }

        $package = $this->promotionPackageModel->find($id);

        if ($package === null) {
            throw new RuntimeException("Promotion package #{$id} was not found.");
        }

        if (! $this->belongsToUserUnit((int) $package['organizational_unit_id'])) {
            throw new RuntimeException('You are not authorized to access this promotion package.');
        }

        return $package;
    }
}
