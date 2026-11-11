<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UnitModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * The companies offered under each CDC on the sign-up form's "Select
 * Company" dropdown (client: "pwede maka edit og company og makaadd").
 *
 * Reference Data could already do this, but only for an administrator and
 * buried among every other unit. This screen is for the CDC side as well
 * (`companies.manage`), shows only companies, and needs nothing but a CDC
 * and a name -- the unit code is made up here.
 *
 * A company is an `organizational_units` row of unit_type `company` whose
 * parent is the CDC. Rows are deactivated, never deleted: reservist records
 * point at them. A company that already has reservists cannot be moved to
 * another CDC, because sign-up and My Profile require a reservist's company
 * to belong to their own CDC.
 */
class CompaniesController extends BaseController
{
    private UnitModel $units;

    public function __construct()
    {
        $this->units = new UnitModel();
    }

    public function index(): string
    {
        $cdcs     = $this->cdcs();
        $cdcId    = (int) ($this->request->getGet('cdc') ?? 0);
        $selected = $cdcId > 0 && isset($cdcs[$cdcId]) ? $cdcId : null;

        $query = $this->units
            ->select('organizational_units.*, cdc.unit_name AS cdc_name')
            ->select('(SELECT COUNT(*) FROM reservists r WHERE r.company_unit_id = organizational_units.id AND r.deleted_at IS NULL) AS reservist_count', false)
            ->join('organizational_units AS cdc', 'cdc.id = organizational_units.parent_unit_id')
            ->where('organizational_units.unit_type', UnitModel::COMPANY_UNIT_TYPE)
            ->where('cdc.unit_type', UnitModel::CDC_UNIT_TYPE);

        if ($selected !== null) {
            $query->where('organizational_units.parent_unit_id', $selected);
        }

        return view('admin/companies', [
            'title'     => 'Companies',
            'cdcs'      => $cdcs,
            'selected'  => $selected,
            'companies' => $query->orderBy('cdc.unit_code', 'ASC')->orderBy('organizational_units.unit_name', 'ASC')->findAll(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $cdcId = (int) $this->request->getPost('cdc_id');
        $name  = $this->cleanName($this->request->getPost('company_name'));
        $back  = site_url('admin/companies') . ($cdcId > 0 ? '?cdc=' . $cdcId : '');

        if (($error = $this->check($cdcId, $name, null)) !== null) {
            return redirect()->to($back)->withInput()->with('error', $error);
        }

        $cdc  = $this->cdcs()[$cdcId];
        $data = [
            'parent_unit_id' => $cdcId,
            'unit_code'      => $this->newCode($cdc['unit_code'], $name),
            'unit_name'      => $name,
            'unit_type'      => UnitModel::COMPANY_UNIT_TYPE,
            'is_active'      => 1,
        ];

        if (! $this->units->insert($data)) {
            return redirect()->to($back)->withInput()->with('error', implode(' ', $this->units->errors()));
        }

        $this->audit('company.created', (int) $this->units->getInsertID(), [], $data);

        return redirect()->to($back)->with('success', $name . ' added under ' . $cdc['unit_name'] . '. It now appears on the sign-up form.');
    }

    public function update(?int $id = null): RedirectResponse
    {
        $company = $this->findCompany($id);
        $cdcId   = (int) ($this->request->getPost('cdc_id') ?: $company['parent_unit_id']);
        $name    = $this->cleanName($this->request->getPost('company_name'));
        $back    = site_url('admin/companies') . ($this->request->getPost('return_cdc') ? '?cdc=' . (int) $this->request->getPost('return_cdc') : '');

        if ($cdcId !== (int) $company['parent_unit_id'] && $this->reservistCount((int) $company['id']) > 0) {
            return redirect()->to($back)->with('error', $company['unit_name'] . ' already has reservists, so it stays under its CDC. Add a new company under the other CDC instead.');
        }

        if (($error = $this->check($cdcId, $name, (int) $company['id'])) !== null) {
            return redirect()->to($back)->with('error', $error);
        }

        $data = ['id' => $company['id'], 'unit_name' => $name, 'parent_unit_id' => $cdcId];

        if (! $this->units->update($company['id'], $data)) {
            return redirect()->to($back)->with('error', implode(' ', $this->units->errors()));
        }

        $this->audit('company.updated', (int) $company['id'], ['unit_name' => $company['unit_name'], 'parent_unit_id' => $company['parent_unit_id']], ['unit_name' => $name, 'parent_unit_id' => $cdcId]);

        return redirect()->to($back)->with('success', 'Company saved.');
    }

    public function toggle(?int $id = null): RedirectResponse
    {
        $company = $this->findCompany($id);
        $active  = (int) $company['is_active'] === 1 ? 0 : 1;
        $back    = site_url('admin/companies') . ($this->request->getPost('return_cdc') ? '?cdc=' . (int) $this->request->getPost('return_cdc') : '');

        $this->units->update($company['id'], ['id' => $company['id'], 'is_active' => $active]);
        $this->audit($active === 1 ? 'company.reactivated' : 'company.deactivated', (int) $company['id']);

        return redirect()->to($back)->with('success', $active === 1
            ? $company['unit_name'] . ' is back on the sign-up form.'
            : $company['unit_name'] . ' is hidden from the sign-up form. Reservists already in it keep it.');
    }

    // ------------------------------------------------------------------

    /**
     * Active CDC units keyed by id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cdcs(): array
    {
        $cdcs = $this->units->where('is_active', 1)
            ->where('unit_type', UnitModel::CDC_UNIT_TYPE)
            ->orderBy('unit_code', 'ASC')
            ->findAll();

        return array_column($cdcs, null, 'id');
    }

    /**
     * Returns a message when the CDC or name cannot be used, else null.
     */
    private function check(int $cdcId, string $name, ?int $exceptId): ?string
    {
        if (! isset($this->cdcs()[$cdcId])) {
            return 'Choose the CDC the company belongs to.';
        }

        if ($name === '') {
            return 'Enter the company name.';
        }

        if (mb_strlen($name) > 150) {
            return 'The company name must be 150 characters or fewer.';
        }

        $duplicate = $this->units
            ->where('unit_type', UnitModel::COMPANY_UNIT_TYPE)
            ->where('parent_unit_id', $cdcId)
            // The utf8mb4 *_ci collation already compares case-insensitively.
            ->where('unit_name', $name);

        if ($exceptId !== null) {
            $duplicate->where('id !=', $exceptId);
        }

        return $duplicate->countAllResults() > 0
            ? 'That CDC already has a company with this name.'
            : null;
    }

    private function cleanName(mixed $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $name));
    }

    /**
     * "CDC-1502" + "Foxtrot Company" -> "CDC-1502-F", then -F2, -F3 ... when
     * taken. Codes are unique across every unit.
     */
    private function newCode(string $cdcCode, string $name): string
    {
        $letter = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $name))[0] ?? 'X';
        $base   = mb_substr($cdcCode, 0, 26) . '-' . $letter;
        $code   = $base;

        for ($n = 2; (new UnitModel())->where('unit_code', $code)->countAllResults() > 0; $n++) {
            $code = $base . $n;
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function findCompany(?int $id): array
    {
        $company = $id !== null ? $this->units->find($id) : null;

        if ($company === null || $company['unit_type'] !== UnitModel::COMPANY_UNIT_TYPE) {
            throw PageNotFoundException::forPageNotFound('That company was not found.');
        }

        return $company;
    }

    private function reservistCount(int $companyId): int
    {
        return db_connect()->table('reservists')
            ->where('company_unit_id', $companyId)
            ->where('deleted_at', null)
            ->countAllResults();
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function audit(string $action, int $id, array $old = [], array $new = []): void
    {
        $user = $this->currentUser();

        service('auditLogService')->record($user !== null ? (int) $user->id : null, $action, 'organizational_unit', $id, $old, $new);
    }
}
