<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The individual line items (A-M) within a document requirement
 * template. `is_conditional` + `condition_expression` capture Table
 * 4's conditional items (e.g. Item K/PRC-CSC applies only to licensed
 * professionals; Item I/Awards allows a commander override) --
 * `condition_expression` is a short machine-readable tag consumed by
 * `DocumentValidationService`, not a general expression language.
 */
class CreateDocumentRequirementsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                                => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'document_requirement_template_id'   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'document_code'                      => ['type' => 'char', 'constraint' => 1, 'comment' => 'A through M'],
            'document_label'                      => ['type' => 'varchar', 'constraint' => 200],
            'is_conditional'                       => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'condition_expression'                => ['type' => 'varchar', 'constraint' => 100, 'null' => true],
            'allows_commander_override'           => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'display_order'                       => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at'                          => ['type' => 'datetime', 'null' => true],
            'updated_at'                          => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['document_requirement_template_id', 'document_code']);
        $this->forge->addForeignKey('document_requirement_template_id', 'document_requirement_templates', 'id', '', 'CASCADE');
        $this->forge->createTable('document_requirements', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_requirements', true);
    }
}
