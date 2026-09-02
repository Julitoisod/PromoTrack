<?php

namespace App\Controllers;

use App\Models\PackageStatusHistoryModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionOrderModel;
use App\Models\PromotionPackageModel;

/**
 * The role-scoped landing dashboard (design doc's Dashboard &
 * Analytics Module, Objective 3): real-time summary tiles plus a
 * package-pipeline breakdown, scoped to the current user's own unit
 * for unit_s1_clerk and cross-unit for every other role.
 */
class Home extends BaseController
{
    public function index(): string
    {
        $user = $this->currentUser();

        $unitScopedId = $user !== null && $user->inGroup('unit_s1_clerk')
            ? (int) $user->organizational_unit_id
            : null;

        $assessmentModel = new PromotionAssessmentModel();
        $packageModel    = new PromotionPackageModel();
        $orderModel      = new PromotionOrderModel();
        $historyModel    = new PackageStatusHistoryModel();

        $statusCounts  = $assessmentModel->latestStatusCounts($unitScopedId);
        $packageCounts = $packageModel->countsByStatus($unitScopedId);

        $pendingPackages = array_sum(array_diff_key($packageCounts, array_flip(['closed', 'archived', 'disapproved'])));

        return view('dashboard', [
            'title'               => 'Dashboard',
            'eligibleCount'       => $statusCounts['eligible'] ?? 0,
            'overdueCount'        => $statusCounts['overdue'] ?? 0,
            'notYetEligibleCount' => $statusCounts['not_yet_eligible'] ?? 0,
            'pendingPackages'     => $pendingPackages,
            'promotionsIssued'    => $orderModel->countForUnit($unitScopedId),
            'packageCounts'       => $packageCounts,

            // The two questions a commander actually asks of this screen:
            // are promotions moving, and where do packages get stuck.
            'promotionsByMonth'   => $orderModel->monthlyTotals($unitScopedId),
            'averageDaysInStatus' => $historyModel->averageDaysInStatus($unitScopedId),
        ]);
    }
}
