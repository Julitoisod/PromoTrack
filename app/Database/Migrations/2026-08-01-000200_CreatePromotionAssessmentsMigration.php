<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Immutable snapshots of `EligibilityService::evaluate()` results (design
 * doc §5.5). A promotion package (Phase C) points at the assessment that
 * justified creating it, so historical assessments must remain
 * reproducible even after the promotion rule they were computed against
 * is later revised.
 */
class CreatePromotionAssessmentsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'from_rank_id'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'to_rank_id'                => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'education_track_id'        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'promotion_rule_id'         => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'promotion_rule_version_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'as_of_date'                => ['type' => 'date'],
            'time_in_grade_months'      => ['type' => 'int', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'promotion_status'          => ['type' => 'varchar', 'constraint' => 20, 'null' => true, 'comment' => 'not_yet_eligible, eligible, overdue; null when unverified'],
            'is_eligible'               => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'requires_manual_review'    => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'snapshot_json'             => ['type' => 'text', 'comment' => 'Full immutable EligibilityService::evaluate() result'],
            'assessed_by'               => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'verified_by'               => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'verified_at'               => ['type' => 'datetime', 'null' => true],
            'created_at'                => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('reservist_id');
        $this->forge->addKey('promotion_status');
        $this->forge->addKey(['reservist_id', 'to_rank_id']);
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('from_rank_id', 'ranks', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('to_rank_id', 'ranks', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('education_track_id', 'education_tracks', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('promotion_rule_id', 'promotion_rules', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('promotion_rule_version_id', 'promotion_rule_versions', 'id', '', 'SET NULL');
        $this->forge->createTable('promotion_assessments', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('promotion_assessments', true);
    }
}
