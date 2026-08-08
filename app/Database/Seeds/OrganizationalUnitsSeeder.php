<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds a minimal organizational-unit hierarchy so the application has
 * usable data to develop and test against.
 *
 * The unit codes/names below are generic PLACEHOLDER development data
 * only. The actual AFP Reserve Force unit structure, unit codes, and
 * unit hierarchy REQUIRE OFFICIAL AFP/PHILIPPINE ARMY VALIDATION before
 * this seeder is used against a production system.
 */
class OrganizationalUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('organizational_units')->insert([
            'parent_unit_id' => null,
            'unit_code'      => 'HHQ-SAMPLE',
            'unit_name'      => 'Higher Headquarters (Sample)',
            'unit_type'      => 'headquarters',
            'is_active'      => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $headquartersId = $this->db->insertID();

        $subordinateUnits = [
            [
                'unit_code' => 'RCDG-SAMPLE',
                'unit_name' => 'Reserve Command Development Group (Sample)',
                'unit_type' => 'rcdg',
            ],
            [
                'unit_code' => 'CDC-SAMPLE',
                'unit_name' => 'Career Development Center (Sample)',
                'unit_type' => 'cdc',
            ],
            [
                'unit_code' => 'UNIT-A-SAMPLE',
                'unit_name' => 'Sample Reserve Unit A',
                'unit_type' => 'reserve_unit',
            ],
            [
                'unit_code' => 'UNIT-B-SAMPLE',
                'unit_name' => 'Sample Reserve Unit B',
                'unit_type' => 'reserve_unit',
            ],
        ];

        foreach ($subordinateUnits as $unit) {
            $this->db->table('organizational_units')->insert([
                'parent_unit_id' => $headquartersId,
                'unit_code'      => $unit['unit_code'],
                'unit_name'      => $unit['unit_name'],
                'unit_type'      => $unit['unit_type'],
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }
}
