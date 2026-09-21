<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EducationTrackModel;
use App\Models\RankModel;
use App\Models\TrainingTypeModel;
use App\Models\UnitModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;

/**
 * Maintains the four reference tables the rest of the system selects from:
 * organizational units, ranks, training types, and education tracks.
 *
 * Until this existed the only way to add a unit or correct a rank name was to
 * edit a seeder and re-run it against live data. Rows are deactivated rather
 * than deleted: every one of them is referenced by reservist and package rows
 * that must keep resolving, and `is_active` is already what the pickers filter
 * on.
 *
 * One controller for four tables, driven by self::SETS -- they differ only in
 * their model, their columns, and their labels.
 */
class ReferenceDataController extends BaseController
{
    /**
     * @var array<string, array{
     *     model: class-string<Model>,
     *     title: string,
     *     singular: string,
     *     columns: array<string, array{label: string, type: string, required?: bool, help?: string}>
     * }>
     */
    private const SETS = [
        'units' => [
            'model'    => UnitModel::class,
            'title'    => 'Organizational Units',
            'singular' => 'Unit',
            'columns'  => [
                'unit_code' => ['label' => 'Unit code', 'type' => 'text', 'required' => true],
                'unit_name' => ['label' => 'Unit name', 'type' => 'text', 'required' => true],
                'unit_type' => ['label' => 'Type', 'type' => 'text', 'help' => 'e.g. RCDG, CDC, battalion. Use "company" for Alpha/Bravo/Charlie — those appear in the reservist company picker rather than the unit picker.'],
            ],
        ],
        'ranks' => [
            'model'    => RankModel::class,
            'title'    => 'Ranks',
            'singular' => 'Rank',
            'columns'  => [
                'rank_code'     => ['label' => 'Rank code', 'type' => 'text', 'required' => true],
                'rank_name'     => ['label' => 'Rank name', 'type' => 'text', 'required' => true],
                'rank_category' => ['label' => 'Category', 'type' => 'text', 'help' => 'enlisted / non-commissioned / commissioned'],
                'sort_order'    => ['label' => 'Order', 'type' => 'number', 'required' => true, 'help' => 'Low to high; promotion direction is decided by this value.'],
            ],
        ],
        'training-types' => [
            'model'    => TrainingTypeModel::class,
            'title'    => 'Training Types',
            'singular' => 'Training type',
            'columns'  => [
                'training_code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'training_name' => ['label' => 'Training name', 'type' => 'text', 'required' => true],
                'is_mles'       => ['label' => 'MLES-qualifying', 'type' => 'checkbox', 'help' => 'Counts toward the MLES eligibility criterion.'],
                'description'   => ['label' => 'Description', 'type' => 'text'],
            ],
        ],
        'education-tracks' => [
            'model'    => EducationTrackModel::class,
            'title'    => 'Education Tracks',
            'singular' => 'Education track',
            'columns'  => [
                'track_code'  => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'track_name'  => ['label' => 'Track name', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'text'],
            ],
        ],
    ];

    /**
     * Lists every row of one reference set, active and inactive.
     */
    public function index(string $set = 'units'): string
    {
        $config = $this->configFor($set);
        $model  = $this->modelFor($config);

        return view('admin/reference_data', [
            'title'       => $config['title'],
            'set'      => $set,
            'sets'     => $this->setIndex(),
            'config'   => $config,
            'rows'     => $model->orderBy($this->displayOrderColumn($config), 'ASC')->findAll(),
        ]);
    }

    /**
     * Creates a row in the given set.
     */
    public function store(string $set): RedirectResponse
    {
        $config = $this->configFor($set);
        $model  = $this->modelFor($config);

        // New rows are active so they appear in the pickers immediately.
        if (! $model->insert($this->collect($config) + ['is_active' => 1])) {
            return redirect()->back()->withInput()
                ->with('errors', $model->errors())
                ->with('error', $config['singular'] . ' could not be added. Please review the errors below.');
        }

        return redirect()->to(site_url('admin/reference/' . $set))
            ->with('success', $config['singular'] . ' added.');
    }

    /**
     * Updates a row in the given set.
     */
    public function update(string $set, ?int $id = null): RedirectResponse
    {
        $config = $this->configFor($set);
        $model  = $this->modelFor($config);
        $row    = $this->findOrFail($model, $id);

        $data = $this->collect($config);

        // Included only so the model's `is_unique[...,id,{id}]` rule resolves
        // to this row; `id` is not in $allowedFields, so it is stripped from
        // the SQL update itself.
        $data['id'] = $row['id'];

        if (! $model->update($row['id'], $data)) {
            return redirect()->back()->withInput()
                ->with('errors', $model->errors())
                ->with('error', $config['singular'] . ' could not be updated. Please review the errors below.');
        }

        return redirect()->to(site_url('admin/reference/' . $set))
            ->with('success', $config['singular'] . ' updated.');
    }

    /**
     * Flips a row between active and inactive.
     *
     * Reference rows are never deleted: reservist, package, and assessment
     * rows point at them and must keep resolving after the row stops being
     * offered in new pickers.
     */
    public function toggle(string $set, ?int $id = null): RedirectResponse
    {
        $config = $this->configFor($set);
        $model  = $this->modelFor($config);
        $row    = $this->findOrFail($model, $id);

        $nowActive = ((int) ($row['is_active'] ?? 0)) === 1 ? 0 : 1;

        $model->update($row['id'], ['is_active' => $nowActive, 'id' => $row['id']]);

        return redirect()->to(site_url('admin/reference/' . $set))
            ->with('success', $config['singular'] . ' ' . ($nowActive === 1 ? 'reactivated' : 'deactivated') . '.');
    }

    /**
     * Builds the row data from the request, limited to the columns this set
     * declares. `is_active` defaults to 1 on create so a new row is usable
     * immediately.
     *
     * @param array{columns: array<string, array{label: string, type: string, required?: bool, help?: string}>} $config
     */
    private function collect(array $config): array
    {
        $data = [];

        foreach ($config['columns'] as $column => $meta) {
            $data[$column] = $meta['type'] === 'checkbox'
                ? ($this->request->getPost($column) !== null ? 1 : 0)
                : ($this->request->getPost($column) ?: null);
        }

        // `is_active` is deliberately absent: it is owned by toggle(), so
        // editing a deactivated row does not quietly bring it back.
        return $data;
    }

    /**
     * The column each set is listed by: ranks read in promotion order,
     * everything else alphabetically.
     */
    private function displayOrderColumn(array $config): string
    {
        return isset($config['columns']['sort_order']) ? 'sort_order' : array_key_first($config['columns']);
    }

    /**
     * @return array<string, string> slug => title, for the set switcher
     */
    private function setIndex(): array
    {
        $index = [];

        foreach (self::SETS as $slug => $config) {
            $index[$slug] = $config['title'];
        }

        return $index;
    }

    private function configFor(string $set): array
    {
        if (! isset(self::SETS[$set])) {
            throw PageNotFoundException::forPageNotFound('Unknown reference data set: ' . $set);
        }

        return self::SETS[$set];
    }

    private function modelFor(array $config): Model
    {
        $class = $config['model'];

        return new $class();
    }

    private function findOrFail(Model $model, ?int $id): array
    {
        $row = $id === null ? null : $model->find($id);

        if ($row === null) {
            throw PageNotFoundException::forPageNotFound('That reference row was not found.');
        }

        return $row;
    }
}
