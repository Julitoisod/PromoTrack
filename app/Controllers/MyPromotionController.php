<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageDocumentModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionPackageModel;
use App\Models\PromotionRuleModel;
use App\Models\RankModel;
use App\Models\ReservistModel;
use App\Models\UnitModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * The reservist's own view of their promotion: where they stand, and
 * the thirteen-item checklist they upload their documents into.
 *
 * Deliberately separate from `PromotionPackagesController`, which is the
 * staff surface: this controller never takes a record id from the URL,
 * so there is no id for a reservist to tamper with. The record is always
 * the one their account owns (`reservists.user_id`), resolved from the
 * session. Uploads still post to `PackageDocumentsController`, which
 * re-checks ownership through `BaseController::canAccessPackageRow()`.
 */
class MyPromotionController extends BaseController
{
    public function index(): string
    {
        $reservistId = $this->ownReservistId();
        $reservist   = $reservistId !== null ? (new ReservistModel())->find($reservistId) : null;

        if ($reservist === null) {
            // The account exists but no reservist record is linked to it --
            // sign-up could not match a serial and could not create one.
            // Say so plainly instead of rendering an empty checklist.
            return view('my/promotion', [
                'title'      => 'My Promotion',
                'reservist'  => null,
                'package'    => null,
                'checklist'  => [],
                'assessment' => null,
                'unit'       => null,
                'company'    => null,
                'rank'       => null,
                'missing'    => [],
            ]);
        }

        $package = (new PromotionPackageModel())
            ->where('reservist_id', $reservist->id)
            ->orderBy('id', 'DESC')
            ->first();

        $unitModel = new UnitModel();

        return view('my/promotion', [
            'title'      => 'My Promotion',
            'reservist'  => $reservist,
            'package'    => $package,
            'checklist'  => $package !== null
                ? (new PackageDocumentModel())->checklistFor((int) $package['id'])
                : [],
            'missing'    => $package !== null
                ? service('documentValidationService')->missingItems((int) $package['id'])
                : [],
            'assessment' => (new PromotionAssessmentModel())->latestFor($reservist->id),
            'unit'       => $reservist->organizational_unit_id !== null
                ? $unitModel->find($reservist->organizational_unit_id)
                : null,
            'company'    => $reservist->company_unit_id !== null
                ? $unitModel->find($reservist->company_unit_id)
                : null,
            'rank'       => $reservist->current_rank_id !== null
                ? (new RankModel())->find($reservist->current_rank_id)
                : null,
        ]);
    }

    /**
     * Opens the reservist's own promotion package so they can start
     * uploading the A-M checklist.
     *
     * The package used to be the unit S1 clerk's to create, which left a
     * reservist who had just signed up looking at "you have no open
     * promotion package" with no way forward and nothing to upload into
     * -- the exact opposite of the requirement that the reservist
     * uploads their own documents. They can now start it themselves; the
     * clerk still verifies every document and still owns the submission
     * to the CDC, so nothing leaves the unit unchecked.
     *
     * No id is taken from the request: the package is always for the
     * record this account owns. `PackageWorkflowService::create()`
     * enforces one open package per reservist, so pressing this twice
     * cannot produce a second one.
     */
    public function openPackage(): RedirectResponse
    {
        $reservistId = $this->ownReservistId();
        $reservist   = $reservistId !== null ? (new ReservistModel())->find($reservistId) : null;

        if ($reservist === null) {
            return redirect()->to(site_url('my/promotion'))
                ->with('error', 'Your account is not linked to a reservist record yet.');
        }

        if ($reservist->organizational_unit_id === null) {
            return redirect()->to(site_url('my/promotion'))
                ->with('error', 'Your record has no unit on file, so a package cannot be opened. Please contact your unit S1 clerk.');
        }

        // Mirrors PromotionPackagesController::store(): the target rank and
        // rule come from the single active rule for the current rank. Both
        // are legitimately null for a reservist with no rank recorded yet,
        // and the checklist then comes from the rank-agnostic template --
        // which is the whole A-M list, so there is still everything to
        // upload.
        $rule = $reservist->current_rank_id !== null
            ? (new PromotionRuleModel())->findActiveRuleForFromRank((int) $reservist->current_rank_id)
            : null;

        $assessment = $rule !== null
            ? (new PromotionAssessmentModel())->mostRecentFor($reservist->id, (int) $rule['to_rank_id'])
            : null;

        $user = $this->currentUser();

        try {
            service('packageWorkflowService')->create(
                $reservist->id,
                (int) $reservist->organizational_unit_id,
                isset($assessment['id']) ? (int) $assessment['id'] : null,
                isset($rule['id']) ? (int) $rule['id'] : null,
                $user !== null ? (int) $user->id : 0
            );
        } catch (RuntimeException) {
            // create() refuses for exactly one reason -- an open package
            // already exists -- and its message is written for staff
            // ("this reservist"). On the reservist's own page that reads
            // like it is about somebody else.
            return redirect()->to(site_url('my/promotion'))
                ->with('error', 'You already have an open promotion package.');
        }

        return redirect()->to(site_url('my/promotion'))
            ->with('success', 'Your promotion package is open. Upload each of the thirteen documents below.');
    }
}
