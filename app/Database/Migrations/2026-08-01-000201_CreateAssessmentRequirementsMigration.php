<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-criterion breakdown of a `promotion_assessments` row (one row per
 * entry in `EligibilityService::evaluate()`'s `criteria` list), so a
 * reviewer can see exactly which criterion blocked eligibility without
 * parsing `snapshot_json`.
 */
class CreateAssessmentRequirementsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'promotion_assessment_id'   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'requirement_code'          => ['type' => 'varchar', 'constraint' => 60, 'comment' => 'matches EligibilityService criterion key, e.g. time_in_grade, mles, pft'],
            'requirement_label'         => ['type' => 'varchar', 'constraint' => 150],
            'is_satisfied'              => ['type' => 'tinyint', 'constraint' => 1, 'null' => true, 'comment' => 'null = unverified/requires manual review'],
            'remarks'                   => ['type' => 'text', 'null' => true],
            'created_at'                => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('promotion_assessment_id');
        $this->forge->addForeignKey('promotion_assessment_id', 'promotion_assessments', 'id', '', 'CASCADE');
        $this->forge->createTable('assessment_requirements', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('assessment_requirements', true);
    }
}
