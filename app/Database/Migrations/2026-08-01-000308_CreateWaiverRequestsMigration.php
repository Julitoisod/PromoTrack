<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWaiverRequestsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'package_document_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'requested_by'        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'justification'       => ['type' => 'text'],
            'approved_by'         => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'decision'            => ['type' => 'varchar', 'constraint' => 20, 'default' => 'pending', 'comment' => 'pending, approved, denied'],
            'decided_at'          => ['type' => 'datetime', 'null' => true],
            'created_at'          => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('package_document_id');
        $this->forge->addForeignKey('package_document_id', 'package_documents', 'id', '', 'CASCADE');
        $this->forge->createTable('waiver_requests', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('waiver_requests', true);
    }
}
