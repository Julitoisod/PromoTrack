<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A named, versioned set of document requirements (the 13-item Items
 * A-M checklist, Table 4 of the thesis). `promotion_rule_id` is nullable
 * so a single template can apply across every rank transition when the
 * checklist doesn't vary by rank.
 */
class CreateDocumentRequirementTemplatesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_rule_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'template_name'     => ['type' => 'varchar', 'constraint' => 150],
            'version'           => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'is_active'         => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'datetime', 'null' => true],
            'updated_at'        => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_rule_id');
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('promotion_rule_id', 'promotion_rules', 'id', '', 'CASCADE');
        $this->forge->createTable('document_requirement_templates', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_requirement_templates', true);
    }
}
