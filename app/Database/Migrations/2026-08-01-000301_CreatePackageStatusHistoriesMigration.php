<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePackageStatusHistoriesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id'    => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'from_status'             => ['type' => 'varchar', 'constraint' => 40, 'null' => true],
            'to_status'               => ['type' => 'varchar', 'constraint' => 40],
            'changed_by'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'remarks'                 => ['type' => 'text', 'null' => true],
            'created_at'              => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_package_id');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->createTable('package_status_histories', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('package_status_histories', true);
    }
}
