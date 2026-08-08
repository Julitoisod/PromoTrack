<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the education-track dimension that the AFP Minimum Length of
 * Enlisted Status (MLES) matrix requires: the same rank transition can
 * carry a different minimum-time-in-grade threshold per education track
 * (General, Completed 1st Year College, ROTC Professional). A null
 * `education_track_id` means the version applies regardless of track
 * (used for rank transitions whose thresholds do not vary by track).
 */
class AddEducationTrackToPromotionRuleVersionsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('promotion_rule_versions', [
            'education_track_id' => [
                'type'       => 'bigint',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'promotion_rule_id',
            ],
        ]);

        $this->db->query('ALTER TABLE `promotion_rule_versions` ADD KEY `promotion_rule_versions_education_track_id_index` (`education_track_id`)');
        $this->db->query('ALTER TABLE `promotion_rule_versions` ADD CONSTRAINT `promotion_rule_versions_education_track_id_foreign` FOREIGN KEY (`education_track_id`) REFERENCES `education_tracks` (`id`) ON DELETE CASCADE');

        $this->db->query(
            'ALTER TABLE `promotion_rule_versions` DROP INDEX `promotion_rule_id_version_number`,'
            . ' ADD UNIQUE INDEX `promotion_rule_id_version_number` (`promotion_rule_id`, `education_track_id`, `version_number`)'
        );
    }

    public function down(): void
    {
        // Deliberately does NOT restore the original 2-column unique index
        // (promotion_rule_id, version_number): once any data exists with
        // multiple tracks sharing a version_number for the same rule --
        // which is the whole point of this migration, and exactly what
        // PromotionRuleVersionsSeeder inserts -- that narrower constraint
        // can no longer be satisfied. A plain (non-unique) index is kept
        // for lookup performance instead.
        $this->db->query(
            'ALTER TABLE `promotion_rule_versions` DROP INDEX `promotion_rule_id_version_number`,'
            . ' ADD INDEX `promotion_rule_id_version_number` (`promotion_rule_id`, `version_number`)'
        );
        $this->db->query('ALTER TABLE `promotion_rule_versions` DROP FOREIGN KEY `promotion_rule_versions_education_track_id_foreign`');
        $this->forge->dropColumn('promotion_rule_versions', 'education_track_id');
    }
}
