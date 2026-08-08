<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCurrentVersionFkToPackageDocumentsMigration extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `package_documents` ADD KEY `package_documents_current_version_id_index` (`current_version_id`)');
        $this->db->query('ALTER TABLE `package_documents` ADD CONSTRAINT `package_documents_current_version_id_foreign` FOREIGN KEY (`current_version_id`) REFERENCES `document_versions` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `package_documents` DROP FOREIGN KEY `package_documents_current_version_id_foreign`');
        $this->db->query('ALTER TABLE `package_documents` DROP INDEX `package_documents_current_version_id_index`');
    }
}
