<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePromotionOrdersMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'reservist_id'         => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'order_number'         => ['type' => 'varchar', 'constraint' => 100],
            'order_date'           => ['type' => 'date'],
            'effective_date'       => ['type' => 'date'],
            'new_rank_id'          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'recorded_by'          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'           => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('order_number');
        $this->forge->addUniqueKey('promotion_package_id');
        $this->forge->addKey('reservist_id');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('new_rank_id', 'ranks', 'id', '', 'RESTRICT');
        $this->forge->createTable('promotion_orders', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('promotion_orders', true);
    }
}
