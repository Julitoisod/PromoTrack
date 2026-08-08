<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per required document item, instantiated for a specific
 * promotion package. `current_version_id` intentionally has no FK yet
 * (document_versions references package_documents, so the FK is added
 * afterward by AddCurrentVersionFkToPackageDocumentsMigration once that
 * table exists).
 */
class CreatePackageDocumentsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_package_id'      => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'document_requirement_id'   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'status'                    => ['type' => 'varchar', 'constraint' => 20, 'default' => 'pending', 'comment' => 'pending, uploaded, verified, rejected, waived'],
            'current_version_id'        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'                => ['type' => 'datetime', 'null' => true],
            'updated_at'                => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['promotion_package_id', 'document_requirement_id']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('promotion_package_id', 'promotion_packages', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('document_requirement_id', 'document_requirements', 'id', '', 'RESTRICT');
        $this->forge->createTable('package_documents', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('package_documents', true);
    }
}
