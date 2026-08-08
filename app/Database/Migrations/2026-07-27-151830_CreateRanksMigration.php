<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRanksMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'rank_code'   => ['type' => 'varchar', 'constraint' => 20],
            'rank_name'   => ['type' => 'varchar', 'constraint' => 100],
            'rank_category' => ['type' => 'varchar', 'constraint' => 30, 'null' => true, 'comment' => 'e.g. enlisted, non-commissioned, commissioned - REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION'],
            'sort_order'  => ['type' => 'int', 'constraint' => 11, 'default' => 0],
            'is_active'   => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'datetime', 'null' => true],
            'updated_at'  => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('rank_code');
        $this->forge->createTable('ranks', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('ranks', true);
    }
}
