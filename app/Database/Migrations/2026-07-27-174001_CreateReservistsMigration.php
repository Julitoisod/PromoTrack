<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReservistsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_number'        => ['type' => 'varchar', 'constraint' => 30, 'comment' => 'Internal PromoTrack reservist number'],
            'afp_serial_number'       => ['type' => 'varchar', 'constraint' => 30, 'null' => true, 'comment' => 'Official AFP serial number, format REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION'],
            'first_name'              => ['type' => 'varchar', 'constraint' => 100],
            'middle_name'             => ['type' => 'varchar', 'constraint' => 100, 'null' => true],
            'last_name'               => ['type' => 'varchar', 'constraint' => 100],
            'suffix'                  => ['type' => 'varchar', 'constraint' => 10, 'null' => true],
            'sex'                     => ['type' => 'varchar', 'constraint' => 10, 'null' => true],
            'date_of_birth'           => ['type' => 'date', 'null' => true],
            'date_of_enlistment'      => ['type' => 'date', 'null' => true],
            'current_rank_id'         => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'date_of_last_promotion'  => ['type' => 'date', 'null' => true],
            'education_track_id'      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'organizational_unit_id'  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'contact_number'          => ['type' => 'varchar', 'constraint' => 30, 'null' => true],
            'email'                   => ['type' => 'varchar', 'constraint' => 150, 'null' => true],
            'address'                 => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'status'                  => ['type' => 'varchar', 'constraint' => 20, 'default' => 'active', 'comment' => 'active, inactive, discharged, deceased'],
            'created_by'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'              => ['type' => 'datetime', 'null' => true],
            'updated_at'              => ['type' => 'datetime', 'null' => true],
            'deleted_at'              => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('reservist_number');
        $this->forge->addUniqueKey('afp_serial_number');
        $this->forge->addKey('current_rank_id');
        $this->forge->addKey('education_track_id');
        $this->forge->addKey('organizational_unit_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('current_rank_id', 'ranks', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('education_track_id', 'education_tracks', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('organizational_unit_id', 'organizational_units', 'id', '', 'SET NULL');
        $this->forge->createTable('reservists', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('reservists', true);
    }
}
