<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrganizationalUnitsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'parent_unit_id'   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'unit_code'        => ['type' => 'varchar', 'constraint' => 30],
            'unit_name'        => ['type' => 'varchar', 'constraint' => 150],
            'unit_type'        => ['type' => 'varchar', 'constraint' => 50, 'null' => true],
            'is_active'        => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'created_at'       => ['type' => 'datetime', 'null' => true],
            'updated_at'       => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('unit_code');
        $this->forge->addKey('parent_unit_id');
        $this->forge->addForeignKey('parent_unit_id', 'organizational_units', 'id', '', 'SET NULL');
        $this->forge->createTable('organizational_units', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('organizational_units', true);
    }
}
