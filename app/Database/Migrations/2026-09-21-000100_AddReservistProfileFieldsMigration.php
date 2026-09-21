<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the four reservist profile fields the original schema had no
 * place for:
 *
 *  - `user_id`             the Shield account a reservist signs in with,
 *                          so a self-registered reservist can reach their
 *                          own package and upload their own documents.
 *                          Unique: one account owns at most one record.
 *  - `company_unit_id`     the reservist's company (Alpha, Bravo, ...).
 *                          A company is an `organizational_units` row of
 *                          `unit_type = 'company'` parented to the unit,
 *                          so Reference Data already maintains them and
 *                          each unit keeps its own list. The battalion
 *                          stays in `organizational_unit_id`, which is
 *                          what UnitAccessFilter scopes on -- putting the
 *                          company there instead would break every
 *                          unit-scoped query.
 *  - `is_student`          whether the reservist is still studying, and
 *  - `student_year_level`  which year they are in when they are.
 */
class AddReservistProfileFieldsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('reservists', [
            // int, not bigint: Shield's `users.id` is `int unsigned`, and a
            // foreign key whose column type differs from its target is
            // rejected outright (errno 150).
            'user_id' => [
                'type'       => 'int',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'id',
                'comment'    => 'Shield account that owns this reservist record',
            ],
            'company_unit_id' => [
                'type'       => 'bigint',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'organizational_unit_id',
                'comment'    => "organizational_units row with unit_type = 'company'",
            ],
            'is_student' => [
                'type'       => 'tinyint',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'education_track_id',
            ],
            'student_year_level' => [
                'type'       => 'varchar',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'is_student',
            ],
        ]);

        $this->db->query('ALTER TABLE `reservists` ADD UNIQUE KEY `reservists_user_id_unique` (`user_id`)');
        $this->db->query('ALTER TABLE `reservists` ADD KEY `reservists_company_unit_id_index` (`company_unit_id`)');
        $this->db->query('ALTER TABLE `reservists` ADD CONSTRAINT `reservists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');
        $this->db->query('ALTER TABLE `reservists` ADD CONSTRAINT `reservists_company_unit_id_foreign` FOREIGN KEY (`company_unit_id`) REFERENCES `organizational_units` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `reservists` DROP FOREIGN KEY `reservists_company_unit_id_foreign`');
        $this->db->query('ALTER TABLE `reservists` DROP FOREIGN KEY `reservists_user_id_foreign`');
        $this->db->query('ALTER TABLE `reservists` DROP KEY `reservists_user_id_unique`');

        $this->forge->dropColumn('reservists', [
            'user_id',
            'company_unit_id',
            'is_student',
            'student_year_level',
        ]);
    }
}
