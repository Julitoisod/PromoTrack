<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTrainingTypesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'training_code' => ['type' => 'varchar', 'constraint' => 30],
            'training_name' => ['type' => 'varchar', 'constraint' => 150],
            'is_mles'     => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0, 'comment' => 'REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION'],
            'description' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'is_active'   => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'datetime', 'null' => true],
            'updated_at'  => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('training_code');
        $this->forge->createTable('training_types', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('training_types', true);
    }
}
