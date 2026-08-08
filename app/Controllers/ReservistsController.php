<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EducationTrackModel;
use App\Models\PhysicalFitnessTestModel;
use App\Models\ProfessionalEligibilityModel;
use App\Models\PromotionAssessmentModel;
use App\Models\RankHistoryModel;
use App\Models\RankModel;
use App\Models\ReservistModel;
use App\Models\ReservistTrainingModel;
use App\Models\TrainingTypeModel;
use App\Models\UnitModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * CRUD controller for reservist profiles.
 *
 * Thin by design: every request is authenticated and permission-checked
 * by the `session` / `permission` route filters (see Config/Routes.php),
 * record-level organizational-unit ownership is enforced by the
 * `unit-access` filter for single-record routes, and this controller
 * additionally re-validates unit ownership at the point of mutation via
 * `BaseController::belongsToUserUnit()` as a defense-in-depth measure.
 * All actual field validation lives in `ReservistModel::$validationRules`.
 */
class ReservistsController extends BaseController
{
    private ReservistModel $reservistModel;
    private UnitModel $unitModel;
    private RankModel $rankModel;
    private EducationTrackModel $educationTrackModel;
    private PromotionAssessmentModel $promotionAssessmentModel;

    public function __construct()
    {
        $this->reservistModel           = new ReservistModel();
        $this->unitModel                = new UnitModel();
        $this->rankModel                = new RankModel();
        $this->educationTrackModel      = new EducationTrackModel();
        $this->promotionAssessmentModel = new PromotionAssessmentModel();
    }

    /**
     * Lists reservists visible to the current user, scoped to their own
     * organizational unit for unit-scoped roles.
     */
    public function index(): string
    {
        $user = $this->currentUser();

        $unitScopedId = $user !== null && $user->inGroup('unit_s1_clerk')
            ? (int) $user->organizational_unit_id
            : null;

        $reservists = $this->reservistModel->listForDisplay($unitScopedId);

        return view('reservists/index', [
            'title'       => 'Reservists',
            'breadcrumbs' => [
                'Dashboard'  => site_url('/'),
                'Reservists' => '',
            ],
            'reservists' => $reservists,
        ]);
    }

    /**
     * Displays the reservist creation form.
     */
    public function create(): string
    {
        return view('reservists/create', [
            'title'       => 'Add Reservist',
            'breadcrumbs' => [
                'Dashboard'  => site_url('/'),
                'Reservists' => site_url('reservists'),
                'Add'        => '',
            ],
            'units'            => $this->unitModel->activeUnits(),
            'ranks'             => $this->rankModel->activeRanksOrdered(),
            'educationTracks'   => $this->educationTrackModel->activeTracks(),
            'restrictedUnitId'  => $this->restrictedUnitIdForCurrentUser(),
        ]);
    }

    /**
     * Persists a new reservist profile.
     */
    public function store(): RedirectResponse
    {
        $data = $this->collectRequestData();

        if (! $this->reservistModel->insert($data, false)) {
            return redirect()->back()->withInput()
                ->with('errors', $this->reservistModel->errors())
                ->with('error', 'The reservist profile could not be created. Please review the errors below.');
        }

        return redirect()->to(site_url('reservists'))
            ->with('success', 'Reservist profile created successfully.');
    }

    /**
     * Displays a single reservist profile.
     */
    public function show(?int $id = null): string
    {
        $reservist = $this->findAccessibleOrFail($id);

        return view('reservists/show', [
            'title'       => 'Reservist Profile',
            'breadcrumbs' => [
                'Dashboard'  => site_url('/'),
                'Reservists' => site_url('reservists'),
                $reservist->getFullName() => '',
            ],
            'reservist' => $reservist,
            'unit'      => $reservist->organizational_unit_id !== null
                ? $this->unitModel->find($reservist->organizational_unit_id)
                : null,
            'rank' => $reservist->current_rank_id !== null
                ? $this->rankModel->find($reservist->current_rank_id)
                : null,
            'educationTrack' => $reservist->education_track_id !== null
                ? $this->educationTrackModel->find($reservist->education_track_id)
                : null,
            'assessments' => $this->promotionAssessmentModel->historyFor($reservist->id),

            // Service records read by EligibilityService, with the lookup
            // lists their add-forms need.
            'trainings'      => (new ReservistTrainingModel())->forReservist($reservist->id),
            'fitnessTests'   => (new PhysicalFitnessTestModel())->forReservist($reservist->id),
            'eligibilities'  => (new ProfessionalEligibilityModel())->forReservist($reservist->id),
            'rankHistory'    => (new RankHistoryModel())->forReservist($reservist->id),
            'trainingTypes'  => (new TrainingTypeModel())->activeTrainingTypes(),
            'ranks'          => $this->rankModel->activeRanksOrdered(),
        ]);
    }

    /**
     * Displays the reservist edit form.
     */
    public function edit(?int $id = null): string
    {
        $reservist = $this->findAccessibleOrFail($id);

        return view('reservists/edit', [
            'title'       => 'Edit Reservist',
            'breadcrumbs' => [
                'Dashboard'  => site_url('/'),
                'Reservists' => site_url('reservists'),
                'Edit'       => '',
            ],
            'reservist'         => $reservist,
            'units'             => $this->unitModel->activeUnits(),
            'ranks'             => $this->rankModel->activeRanksOrdered(),
            'educationTracks'   => $this->educationTrackModel->activeTracks(),
            'restrictedUnitId'  => $this->restrictedUnitIdForCurrentUser(),
        ]);
    }

    /**
     * Applies an update to an existing reservist profile.
     */
    public function update(?int $id = null): RedirectResponse
    {
        $reservist = $this->findAccessibleOrFail($id);
        $data      = $this->collectRequestData();
        unset($data['created_by']); // Never reassign authorship on update.

        // Included only so the model's `is_unique[...,id,{id}]` validation
        // placeholder resolves to the current row (excluding it from the
        // uniqueness check); `doProtectFields()` strips `id` from the
        // actual SQL update since it is not in `$allowedFields`.
        $data['id'] = $reservist->id;

        if (! $this->reservistModel->update($reservist->id, $data)) {
            return redirect()->back()->withInput()
                ->with('errors', $this->reservistModel->errors())
                ->with('error', 'The reservist profile could not be updated. Please review the errors below.');
        }

        return redirect()->to(site_url('reservists/' . $reservist->id))
            ->with('success', 'Reservist profile updated successfully.');
    }

    /**
     * Soft-deletes a reservist profile.
     */
    public function delete(?int $id = null): RedirectResponse
    {
        $reservist = $this->findAccessibleOrFail($id);

        $this->reservistModel->delete($reservist->id);

        return redirect()->to(site_url('reservists'))
            ->with('success', 'Reservist profile removed successfully.');
    }

    /**
     * Loads a reservist by id, enforcing both existence and
     * organizational-unit ownership before returning it. Complements
     * (does not replace) the `unit-access` route filter.
     */
    private function findAccessibleOrFail(?int $id): \App\Entities\Reservist
    {
        if ($id === null) {
            throw new RuntimeException('A reservist id is required.');
        }

        $reservist = $this->reservistModel->find($id);

        if ($reservist === null) {
            throw new RuntimeException("Reservist #{$id} was not found.");
        }

        if (! $this->belongsToUserUnit($reservist->organizational_unit_id)) {
            throw new RuntimeException('You are not authorized to access this reservist record.');
        }

        return $reservist;
    }

    /**
     * Returns the organizational-unit id that a unit-scoped user is
     * restricted to, or null for roles with cross-unit visibility.
     */
    private function restrictedUnitIdForCurrentUser(): ?int
    {
        $user = $this->currentUser();

        if ($user !== null && $user->inGroup('unit_s1_clerk')) {
            return (int) $user->organizational_unit_id;
        }

        return null;
    }

    /**
     * Builds the allowed-field data array from the current request,
     * forcing the organizational unit for unit-scoped users so they
     * cannot submit a profile into a unit other than their own.
     */
    private function collectRequestData(): array
    {
        $request = $this->request;

        $data = [
            'reservist_number'        => $request->getPost('reservist_number'),
            'afp_serial_number'       => $request->getPost('afp_serial_number') ?: null,
            'first_name'              => $request->getPost('first_name'),
            'middle_name'             => $request->getPost('middle_name') ?: null,
            'last_name'               => $request->getPost('last_name'),
            'suffix'                  => $request->getPost('suffix') ?: null,
            'sex'                     => $request->getPost('sex') ?: null,
            'date_of_birth'           => $request->getPost('date_of_birth') ?: null,
            'date_of_enlistment'      => $request->getPost('date_of_enlistment') ?: null,
            'current_rank_id'         => $request->getPost('current_rank_id') ?: null,
            'date_of_last_promotion'  => $request->getPost('date_of_last_promotion') ?: null,
            'education_track_id'      => $request->getPost('education_track_id') ?: null,
            'organizational_unit_id'  => $request->getPost('organizational_unit_id') ?: null,
            'contact_number'          => $request->getPost('contact_number') ?: null,
            'email'                   => $request->getPost('email') ?: null,
            'address'                 => $request->getPost('address') ?: null,
            'status'                  => $request->getPost('status') ?: 'active',
        ];

        $restrictedUnitId = $this->restrictedUnitIdForCurrentUser();

        if ($restrictedUnitId !== null) {
            $data['organizational_unit_id'] = $restrictedUnitId;
        }

        $user = $this->currentUser();

        if ($user !== null) {
            $data['created_by'] = (int) $user->id;
        }

        return $data;
    }
}
