<?php

namespace App\Controllers;

use App\Models\PackageStatusHistoryModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionOrderModel;
use App\Models\PromotionPackageModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * The role-scoped landing dashboard (design doc's Dashboard &
 * Analytics Module, Objective 3): real-time summary tiles plus a
 * package-pipeline breakdown, scoped to the current user's own unit
 * for unit_s1_clerk and cross-unit for every other role.
 */
class Home extends BaseController
{
    /**
     * @return RedirectResponse|string
     */
    public function index()
    {
        // A reservist has no business on the command dashboard -- its
        // tiles count every package in the organization. Their landing
        // page is their own dashboard.
        if ($this->isReservistAccount()) {
            return redirect()->to(site_url('my/dashboard'));
        }

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

            // Admin panel (client brief of 2026-11-10): headline counts and
            // the semester / classification folders, for those who work them.
            'admin'               => $user !== null && $user->can('enrollment.manage')
                ? [
                    'counts'  => (new \App\Models\ReservistModel())->adminCounts(),
                    'folders' => \App\Controllers\Admin\EnrollmentController::folders(),
                ]
                : null,
        ]);
    }
}
