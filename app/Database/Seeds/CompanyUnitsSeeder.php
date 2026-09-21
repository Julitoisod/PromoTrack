<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Models\UnitModel;
use CodeIgniter\Database\Seeder;

/**
 * Seeds the standard rifle-company letters as `organizational_units`
 * rows of `unit_type = 'company'` under every existing reserve unit, so
 * a reservist signing up has a company to choose.
 *
 * Company letters are structural, not a promotion threshold -- nothing
 * here needs AFP validation. Which companies a given unit actually
 * fields does, so these are created per reserve unit and an
 * administrator adds or deactivates them in Reference Data.
 *
 * Idempotent, unlike the other seeders: it skips any company whose code
 * already exists, so it can be re-run after a new reserve unit is added.
 */
class CompanyUnitsSeeder extends Seeder
{
    /** @var list<string> */
    private const COMPANIES = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Headquarters & Service'];

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $parentUnits = $this->db->table('organizational_units')
            ->where('unit_type', 'reserve_unit')
            ->get()
            ->getResultArray();

        foreach ($parentUnits as $parent) {
            foreach (self::COMPANIES as $company) {
                $code = $parent['unit_code'] . '-' . strtoupper(mb_substr($company, 0, 1));

                $exists = $this->db->table('organizational_units')
                    ->where('unit_code', $code)
                    ->countAllResults() > 0;

                if ($exists) {
                    continue;
                }

                $this->db->table('organizational_units')->insert([
                    'parent_unit_id' => $parent['id'],
                    'unit_code'      => $code,
                    'unit_name'      => $company . ' Company',
                    'unit_type'      => UnitModel::COMPANY_UNIT_TYPE,
                    'is_active'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }
}
