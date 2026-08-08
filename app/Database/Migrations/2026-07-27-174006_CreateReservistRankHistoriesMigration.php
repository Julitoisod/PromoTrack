<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReservistRankHistoriesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'rank_id'        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'effective_date' => ['type' => 'date'],
            'order_reference' => ['type' => 'varchar', 'constraint' => 100, 'null' => true, 'comment' => 'Promotion order number that produced this rank, if any'],
            'remarks'        => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_by'     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'datetime', 'null' => true],
            'updated_at'     => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('reservist_id');
        $this->forge->addKey('rank_id');
        $this->forge->addKey('effective_date');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('rank_id', 'ranks', 'id', '', 'RESTRICT');
        $this->forge->createTable('reservist_rank_histories', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('reservist_rank_histories', true);
    }
}
