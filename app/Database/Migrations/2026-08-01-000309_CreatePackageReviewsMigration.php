<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePackageReviewsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                    => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id'  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'reviewer_id'           => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'review_stage'          => ['type' => 'varchar', 'constraint' => 10, 'comment' => 'cdc, rcdg'],
            'decision'              => ['type' => 'varchar', 'constraint' => 20, 'comment' => 'validated, returned, endorsed'],
            'remarks'               => ['type' => 'text', 'null' => true],
            'created_at'            => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_package_id');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->createTable('package_reviews', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('package_reviews', true);
    }
}
