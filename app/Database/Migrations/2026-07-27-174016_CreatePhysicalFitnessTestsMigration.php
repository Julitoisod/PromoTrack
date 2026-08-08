<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePhysicalFitnessTestsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'test_date'     => ['type' => 'date'],
            'result'        => ['type' => 'varchar', 'constraint' => 20, 'comment' => 'pass, fail - passing criteria REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION'],
            'score'         => ['type' => 'decimal', 'constraint' => '5,2', 'null' => true],
            'remarks'       => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_by'    => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'datetime', 'null' => true],
            'updated_at'    => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('reservist_id');
        $this->forge->addKey('test_date');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->createTable('physical_fitness_tests', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('physical_fitness_tests', true);
    }
}
