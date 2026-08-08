<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfileFieldsToUsersMigration extends Migration
{
    public function up(): void
    {
        // NOTE: Shield's users table already provides `status` (varchar),
        // `active` (tinyint), `last_active`, and `deleted_at` (soft delete).
        // These are reused for account-status handling instead of duplicating
        // a new status column. Only PromoTrack-specific profile fields and
        // deactivation metadata are added here.
        $this->forge->addColumn('users', [
            'full_name'              => ['type' => 'varchar', 'constraint' => 150, 'null' => true, 'after' => 'id'],
            'afp_serial_number'      => ['type' => 'varchar', 'constraint' => 30, 'null' => true, 'after' => 'full_name'],
            'organizational_unit_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'afp_serial_number'],
            'deactivated_at'         => ['type' => 'datetime', 'null' => true, 'after' => 'organizational_unit_id'],
            'deactivation_reason'    => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'after' => 'deactivated_at'],
        ]);

        $this->db->query('ALTER TABLE `users` ADD UNIQUE KEY `users_afp_serial_number_unique` (`afp_serial_number`)');
        $this->db->query('ALTER TABLE `users` ADD KEY `users_organizational_unit_id_index` (`organizational_unit_id`)');
        $this->db->query('ALTER TABLE `users` ADD CONSTRAINT `users_organizational_unit_id_foreign` FOREIGN KEY (`organizational_unit_id`) REFERENCES `organizational_units` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `users` DROP FOREIGN KEY `users_organizational_unit_id_foreign`');
        $this->db->query('ALTER TABLE `users` DROP KEY `users_afp_serial_number_unique`');
        $this->forge->dropColumn('users', [
            'full_name',
            'afp_serial_number',
            'organizational_unit_id',
            'deactivated_at',
            'deactivation_reason',
        ]);
    }
}
