<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReservistTrainingsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'training_type_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'training_title'   => ['type' => 'varchar', 'constraint' => 200, 'null' => true],
            'date_completed'   => ['type' => 'date', 'null' => true],
            'issuing_authority' => ['type' => 'varchar', 'constraint' => 150, 'null' => true],
            'certificate_number' => ['type' => 'varchar', 'constraint' => 100, 'null' => true],
            'remarks'          => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_by'       => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'datetime', 'null' => true],
            'updated_at'       => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('reservist_id');
        $this->forge->addKey('training_type_id');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('training_type_id', 'training_types', 'id', '', 'RESTRICT');
        $this->forge->createTable('reservist_trainings', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('reservist_trainings', true);
    }
}
