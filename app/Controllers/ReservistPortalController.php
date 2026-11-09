<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Reservist;
use App\Models\EducationTrackModel;
use App\Models\RankModel;
use App\Models\ReservistModel;
use App\Models\UnitModel;

/**
 * Base for the reservist's own screens (dashboard, profile, ROTC progress,
 * documents, notifications, settings).
 *
 * None of these screens take a record id from the URL: the record is always
 * the one this account owns (`reservists.user_id`), resolved from the
 * session, so there is no id for a reservist to tamper with.
 */
abstract class ReservistPortalController extends BaseController
{
    public const PHOTO_DIR = 'profile_photos';

    private ?Reservist $ownReservistRecord = null;

    /**
     * The reservist record this account owns, or null when sign-up could not
     * link one.
     */
    protected function ownReservist(): ?Reservist
    {
        if ($this->ownReservistRecord === null && ($id = $this->ownReservistId()) !== null) {
            $this->ownReservistRecord = (new ReservistModel())->find($id);
        }

        return $this->ownReservistRecord;
    }

    /**
     * What every portal page shows about the reservist: CDC, company, rank,
     * education track, photo.
     *
     * @return array<string, mixed>
     */
    protected function portalContext(Reservist $reservist): array
    {
        $unitModel = new UnitModel();

        return [
            'reservist' => $reservist,
            'unit'      => $reservist->organizational_unit_id !== null ? $unitModel->find($reservist->organizational_unit_id) : null,
            'company'   => $reservist->company_unit_id !== null ? $unitModel->find($reservist->company_unit_id) : null,
            'rank'      => $reservist->current_rank_id !== null ? (new RankModel())->find($reservist->current_rank_id) : null,
            'track'     => $reservist->education_track_id !== null ? (new EducationTrackModel())->find($reservist->education_track_id) : null,
            'photoUrl'  => $reservist->profile_photo !== null ? site_url('my/photo') . '?v=' . substr(md5((string) $reservist->profile_photo), 0, 8) : null,
        ];
    }

    /**
     * The page shown when the account has no reservist record linked.
     */
    protected function notLinked(string $title): string
    {
        return view('my/not_linked', ['title' => $title]);
    }
}
