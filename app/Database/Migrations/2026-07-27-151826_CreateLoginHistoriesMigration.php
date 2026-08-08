<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLoginHistoriesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'identifier'  => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'ip_address'  => ['type' => 'varchar', 'constraint' => 45],
            'user_agent'  => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'success'     => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'reason'      => ['type' => 'varchar', 'constraint' => 100, 'null' => true],
            'attempted_at' => ['type' => 'datetime'],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('identifier');
        $this->forge->addKey('attempted_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('login_histories', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('login_histories', true);
    }
}
