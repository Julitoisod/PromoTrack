<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PhysicalFitnessTestModel;
use App\Models\ProfessionalEligibilityModel;
use App\Models\RankHistoryModel;
use App\Models\ReservistModel;
use App\Models\ReservistTrainingModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use RuntimeException;

/**
 * Entry point for the four service-record sets attached to a reservist:
 * trainings, physical fitness tests, professional eligibilities, and rank
 * history.
 *
 * These are the tables `EligibilityService` reads when it evaluates a
 * promotion. Without this controller they could only be populated by a seeder,
 * so a real assessment always reported "no MLES-qualifying training on file"
 * and "no physical fitness test on file" no matter what the reservist had
 * actually completed.
 *
 * One controller rather than four: the four record sets differ only in their
 * model and their field list, both of which are declared in self::TYPES. The
 * add/remove pair is deliberate -- these are append-style service records, and
 * a correction is a removal followed by a re-entry, which leaves the audit log
 * showing both halves.
 */
class ReservistRecordsController extends BaseController
{
    /**
     * Per-record-set configuration, keyed by the URL slug.
     *
     * `fields` lists the request fields copied into the row; anything not
     * named here is ignored, and the model's own `$allowedFields` is the
     * second gate.
     *
     * @var array<string, array{model: class-string<Model>, label: string, fields: list<string>}>
     */
    private const TYPES = [
        'trainings' => [
            'model'  => ReservistTrainingModel::class,
            'label'  => 'Training record',
            'fields' => ['training_type_id', 'training_title', 'date_completed', 'issuing_authority', 'certificate_number', 'remarks'],
        ],
        'fitness-tests' => [
            'model'  => PhysicalFitnessTestModel::class,
            'label'  => 'Physical fitness test',
            'fields' => ['test_date', 'result', 'score', 'remarks'],
        ],
        'eligibilities' => [
            'model'  => ProfessionalEligibilityModel::class,
            'label'  => 'Professional eligibility',
            'fields' => ['eligibility_title', 'rating', 'date_obtained', 'issuing_authority', 'remarks'],
        ],
        'rank-history' => [
            'model'  => RankHistoryModel::class,
            'label'  => 'Rank history entry',
            'fields' => ['rank_id', 'effective_date', 'order_reference', 'remarks'],
        ],
    ];

    private ReservistModel $reservistModel;

    public function __construct()
    {
        $this->reservistModel = new ReservistModel();
    }

    /**
     * Adds one record of $type to the reservist.
     */
    public function store(string $type, ?int $reservistId = null): RedirectResponse
    {
        $config    = $this->configFor($type);
        $reservist = $this->findAccessibleOrFail($reservistId);
        $model     = $this->modelFor($config);

        $data = ['reservist_id' => $reservist->id];

        foreach ($config['fields'] as $field) {
            $data[$field] = $this->request->getPost($field) ?: null;
        }

        $user = $this->currentUser();

        if ($user !== null) {
            $data['created_by'] = (int) $user->id;
        }

        if (! $model->insert($data)) {
            return redirect()->back()->withInput()
                ->with('errors', $model->errors())
                ->with('error', $config['label'] . ' could not be added. Please review the errors below.');
        }

        return redirect()->to($this->reservistUrl($reservist->id))
            ->with('success', $config['label'] . ' added.');
    }

    /**
     * Removes one record of $type from the reservist.
     */
    public function delete(string $type, ?int $reservistId = null, ?int $recordId = null): RedirectResponse
    {
        $config    = $this->configFor($type);
        $reservist = $this->findAccessibleOrFail($reservistId);
        $model     = $this->modelFor($config);

        if ($recordId === null) {
            throw new RuntimeException('A record id is required.');
        }

        $record = $model->find($recordId);

        // The record must belong to the reservist named in the URL, otherwise
        // a clerk could delete another unit's record by pairing their own
        // reservist id with someone else's record id. Answering 404 rather
        // than 403 keeps the response identical whether the record is absent
        // or simply not theirs, so this cannot be used to probe for ids.
        if ($record === null || (int) $record['reservist_id'] !== (int) $reservist->id) {
            throw PageNotFoundException::forPageNotFound('That record does not belong to this reservist.');
        }

        $model->delete($recordId);

        return redirect()->to($this->reservistUrl($reservist->id))
            ->with('success', $config['label'] . ' removed.');
    }

    /**
     * @return array{model: class-string<Model>, label: string, fields: list<string>}
     */
    private function configFor(string $type): array
    {
        if (! isset(self::TYPES[$type])) {
            throw PageNotFoundException::forPageNotFound('Unknown reservist record type: ' . $type);
        }

        return self::TYPES[$type];
    }

    /**
     * @param array{model: class-string<Model>, label: string, fields: list<string>} $config
     */
    private function modelFor(array $config): Model
    {
        $class = $config['model'];

        return new $class();
    }

    private function reservistUrl(int $reservistId): string
    {
        return site_url('reservists/' . $reservistId);
    }

    /**
     * Loads the reservist, enforcing existence and organizational-unit
     * ownership. Complements the `unit-access` route filter rather than
     * replacing it.
     */
    private function findAccessibleOrFail(?int $reservistId): \App\Entities\Reservist
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

        return $reservist;
    }
}
