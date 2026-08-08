<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExternalForwardingRecordsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                       => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id'     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'forwarded_by'             => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'external_authority_name'  => ['type' => 'varchar', 'constraint' => 150, 'comment' => 'Group Commander or RESCOM / PA Headquarters'],
            'forwarding_reference_number' => ['type' => 'varchar', 'constraint' => 100],
            'forwarded_date'           => ['type' => 'date'],
            'remarks'                  => ['type' => 'text', 'null' => true],
            'created_at'               => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_package_id');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->createTable('external_forwarding_records', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('external_forwarding_records', true);
    }
}
