<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The promotion package: the unit of work that moves through the
 * CDC -> RCDG -> external-authority workflow (design doc §4, §5.6).
 *
 * `open_marker` implements "at most one open (non-terminal) package per
 * reservist" as a partial-unique-index emulation: it is set to 1 while
 * the package is open and NULL once it reaches a terminal status
 * (`closed`/`archived`), and MySQL's unique index treats multiple NULLs
 * as non-colliding, so any number of a reservist's past (closed)
 * packages can coexist while only one open one can.
 */
class CreatePromotionPackagesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                        => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'promotion_assessment_id'   => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'organizational_unit_id'    => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'package_number'            => ['type' => 'varchar', 'constraint' => 40],
            'status'                    => ['type' => 'varchar', 'constraint' => 40, 'default' => 'draft'],
            'current_routing_level'     => ['type' => 'varchar', 'constraint' => 40, 'default' => 'CDC'],
            'completeness_percentage'   => ['type' => 'decimal', 'constraint' => '5,2', 'default' => 0],
            'open_marker'               => ['type' => 'tinyint', 'constraint' => 1, 'null' => true, 'default' => 1],
            'submitted_by'              => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_by'                => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'                => ['type' => 'datetime', 'null' => true],
            'updated_at'                => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('package_number');
        $this->forge->addUniqueKey(['reservist_id', 'open_marker']);
        $this->forge->addKey('status');
        $this->forge->addKey('organizational_unit_id');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('promotion_assessment_id', 'promotion_assessments', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('organizational_unit_id', 'organizational_units', 'id', '', 'RESTRICT');
        $this->forge->createTable('promotion_packages', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('promotion_packages', true);
    }
}
