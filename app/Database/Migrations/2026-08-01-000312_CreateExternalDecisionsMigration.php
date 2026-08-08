<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records a decision received FROM an external approving authority
 * (Group Commander / RESCOM / PA Headquarters). The system does not
 * render or simulate that decision -- it is recorded by the Records
 * Officer after being received outside the system (design doc §1).
 */
class CreateExternalDecisionsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id'    => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'recorded_by'             => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'decision'                => ['type' => 'varchar', 'constraint' => 20, 'comment' => 'approved, disapproved, deferred'],
            'decision_reference_number' => ['type' => 'varchar', 'constraint' => 100, 'null' => true],
            'decision_date'           => ['type' => 'date'],
            'deciding_authority_name' => ['type' => 'varchar', 'constraint' => 150],
            'remarks'                 => ['type' => 'text', 'null' => true],
            'supporting_document_path' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_at'              => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_package_id');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->createTable('external_decisions', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('external_decisions', true);
    }
}
