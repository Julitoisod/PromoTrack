<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProfessionalEligibilitiesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'eligibility_title' => ['type' => 'varchar', 'constraint' => 150],
            'rating'           => ['type' => 'varchar', 'constraint' => 50, 'null' => true],
            'date_obtained'    => ['type' => 'date', 'null' => true],
            'issuing_authority' => ['type' => 'varchar', 'constraint' => 150, 'null' => true],
            'remarks'          => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_by'       => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'datetime', 'null' => true],
            'updated_at'       => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('reservist_id');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->createTable('professional_eligibilities', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('professional_eligibilities', true);
    }
}
