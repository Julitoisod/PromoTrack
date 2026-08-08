<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Versioned rule content for a promotion_rules row. Only one version per
 * promotion_rule should be effective (non-expired) at any given time; the
 * EligibilityService always resolves and records the exact version used
 * for a given assessment so historical assessments remain reproducible
 * even after the rule is later revised.
 *
 * The numeric thresholds below (minimum_time_in_grade_months,
 * requires_mles) are placeholders and REQUIRE OFFICIAL AFP/PHILIPPINE
 * ARMY VALIDATION before being relied upon for an actual promotion
 * decision.
 */
class CreatePromotionRuleVersionsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                          => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_rule_id'           => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'version_number'              => ['type' => 'int', 'constraint' => 10, 'unsigned' => true],
            'minimum_time_in_grade_months' => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'comment' => 'REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION'],
            'requires_mles'               => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0, 'comment' => 'REQUIRES OFFICIAL AFP/PHILIPPINE ARMY VALIDATION'],
            'requires_physical_fitness_test' => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'additional_prerequisites'    => ['type' => 'text', 'null' => true, 'comment' => 'JSON-encoded list of additional prerequisite descriptors'],
            'effective_date'              => ['type' => 'date'],
            'expiry_date'                 => ['type' => 'date', 'null' => true],
            'is_active'                   => ['type' => 'tinyint', 'constraint' => 1, 'default' => 1],
            'notes'                       => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_by'                  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'                  => ['type' => 'datetime', 'null' => true],
            'updated_at'                  => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['promotion_rule_id', 'version_number']);
        $this->forge->addKey('effective_date');
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('promotion_rule_id', 'promotion_rules', 'id', '', 'CASCADE');
        $this->forge->createTable('promotion_rule_versions', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('promotion_rule_versions', true);
    }
}
