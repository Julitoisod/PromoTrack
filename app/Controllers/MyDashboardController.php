<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PromotionAssessmentModel;
use App\Models\PromotionPackageModel;
use App\Models\RankModel;

/**
 * The reservist's landing page, per the client's "Student Reservist
 * Dashboard" mock-up of 2026-11-09: a welcome banner with their photo, four
 * tiles, the MS31-MS42 progress card, their information, and their current
 * status. The mock-up's Quick Access and Notifications cards are left out on
 * the client's instruction -- the side menu already carries both.
 *
 * A reservist who is not an undergraduate student gets the same layout with
 * their promotion standing where the ROTC cards would be.
 */
class MyDashboardController extends ReservistPortalController
{
    public function index(): string
    {
        $reservist = $this->ownReservist();

        if ($reservist === null) {
            return $this->notLinked('Dashboard');
        }

        $context = $this->portalContext($reservist);

        return view('my/dashboard', $context + [
            'title'      => 'Dashboard',
            'rotc'       => service('rotcProgressService')->summarize($reservist),
            'assessment' => (new PromotionAssessmentModel())->latestFor($reservist->id),
            'package'    => (new PromotionPackageModel())
                ->where('reservist_id', $reservist->id)
                ->orderBy('id', 'DESC')
                ->first(),
            'rcdgLevel'  => RankModel::isRcdgLevel($context['rank']),
        ]);
    }
}
