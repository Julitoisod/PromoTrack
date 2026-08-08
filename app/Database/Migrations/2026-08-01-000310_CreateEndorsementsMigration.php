<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEndorsementsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id'        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'endorsed_by'                 => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'endorsement_reference_number' => ['type' => 'varchar', 'constraint' => 100],
            'endorsement_date'            => ['type' => 'date'],
            'remarks'                     => ['type' => 'text', 'null' => true],
            'created_at'                  => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_package_id');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->createTable('endorsements', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('endorsements', true);
    }
}
