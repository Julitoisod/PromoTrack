<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePromotionRulesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'from_rank_id'    => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'comment' => 'Current rank the reservist holds before promotion'],
            'to_rank_id'      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'comment' => 'Target rank being applied for'],
            'rule_name'       => ['type' => 'varchar', 'constraint' => 150],
            'is_active'       => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'created_by'      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'datetime', 'null' => true],
            'updated_at'      => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['from_rank_id', 'to_rank_id']);
        $this->forge->addForeignKey('from_rank_id', 'ranks', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('to_rank_id', 'ranks', 'id', '', 'RESTRICT');
        $this->forge->createTable('promotion_rules', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('promotion_rules', true);
    }
}
