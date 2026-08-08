<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds commonly recognized reservist training-program names.
 *
 * `is_mles` is deliberately left at `0` (not qualifying) for every row.
 * Which training programs actually satisfy the Military Leadership and
 * Education Schooling (MLES) promotion requirement, and under what
 * conditions, REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION and
 * must not be assumed from the training name alone.
 */
class TrainingTypesSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $trainings = [
            [
                'training_code' => 'BCAT',
                'training_name' => 'Basic Citizen Army Training',
                'description'   => 'MLES applicability REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.',
            ],
            [
                'training_code' => 'ROTC',
                'training_name' => 'Reserve Officer Training Corps',
                'description'   => 'MLES applicability REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.',
            ],
            [
                'training_code' => 'MRT',
                'training_name' => 'Mandatory Reserve Training',
                'description'   => 'MLES applicability REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.',
            ],
            [
                'training_code' => 'CADS',
                'training_name' => 'Career Advanced Development Seminar',
                'description'   => 'MLES applicability REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION.',
            ],
        ];

        foreach ($trainings as $training) {
            $this->db->table('training_types')->insert([
                'training_code' => $training['training_code'],
                'training_name' => $training['training_name'],
                'is_mles'       => 0,
                'description'   => $training['description'],
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
