<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentReviewsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'document_version_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'reviewed_by'         => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'decision'            => ['type' => 'varchar', 'constraint' => 20, 'comment' => 'verified, rejected'],
            'remarks'             => ['type' => 'text', 'null' => true],
            'reviewed_at'         => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('document_version_id');
        $this->forge->addForeignKey('document_version_id', 'document_versions', 'id', '', 'CASCADE');
        $this->forge->createTable('document_reviews', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_reviews', true);
    }
}
