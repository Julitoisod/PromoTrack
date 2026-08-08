<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEducationTracksMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'track_code'  => ['type' => 'varchar', 'constraint' => 20],
            'track_name'  => ['type' => 'varchar', 'constraint' => 100],
            'description' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'is_active'   => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'datetime', 'null' => true],
            'updated_at'  => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('track_code');
        $this->forge->createTable('education_tracks', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('education_tracks', true);
    }
}
