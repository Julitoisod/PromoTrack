<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageDocumentModel;
use App\Models\PromotionAssessmentModel;
use App\Models\PromotionPackageModel;
use App\Models\RankModel;
use App\Models\ReservistModel;
use App\Models\UnitModel;

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
}
