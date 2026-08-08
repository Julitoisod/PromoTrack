<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'        => ['type' => 'varchar', 'constraint' => 100],
            'entity_type'   => ['type' => 'varchar', 'constraint' => 100, 'null' => true],
            'entity_id'     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'old_values'    => ['type' => 'text', 'null' => true],
            'new_values'    => ['type' => 'text', 'null' => true],
            'ip_address'    => ['type' => 'varchar', 'constraint' => 45, 'null' => true],
            'user_agent'    => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'datetime'],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('audit_logs', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
