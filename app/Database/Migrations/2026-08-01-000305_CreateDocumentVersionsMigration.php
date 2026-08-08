<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per uploaded file for a package document. Per the security
 * checklist (design doc §12): `stored_filename` is randomized (never
 * the user-supplied original name) and `storage_path` always resolves
 * under `writable/uploads/`, never `public/` -- see FileStorageService.
 */
class CreateDocumentVersionsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'package_document_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'version_number'      => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'stored_filename'     => ['type' => 'varchar', 'constraint' => 255],
            'original_filename'   => ['type' => 'varchar', 'constraint' => 255],
            'mime_type'           => ['type' => 'varchar', 'constraint' => 150],
            'file_size_bytes'     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'sha256_checksum'     => ['type' => 'char', 'constraint' => 64],
            'storage_path'        => ['type' => 'varchar', 'constraint' => 255],
            'uploaded_by'         => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'uploaded_at'         => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('package_document_id');
        $this->forge->addForeignKey('package_document_id', 'package_documents', 'id', '', 'CASCADE');
        $this->forge->createTable('document_versions', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_versions', true);
    }
}
