<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Client admin-panel brief of 2026-11-10:
 *
 *  - `student_enrollments`: a student reservist's Certificate of Registration
 *    (COR) for one semester, and whether an administrator verified it. One
 *    row per reservist per year level, so last semester's COR stays on file.
 *  - `reservists.reserve_classification`: Standby or Ready Reserve as
 *    assigned by an administrator. When it is empty the classification the
 *    student reported on their ROTC ladder is used instead.
 */
class AddEnrollmentAndClassificationMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('reservists', [
            'reserve_classification' => [
                'type'       => 'varchar',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'student_year_level',
                'comment'    => 'standby, ready_reserve; set by an administrator',
            ],
            'classification_updated_by' => [
                'type'       => 'int',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'reserve_classification',
            ],
            'classification_updated_at' => [
                'type'  => 'datetime',
                'null'  => true,
                'after' => 'classification_updated_by',
            ],
        ]);

        $this->forge->addField([
            'id'           => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id' => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'year_level'   => ['type' => 'varchar', 'constraint' => 20],
            'cor_path'     => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'comment' => 'File name under writable/uploads/cor'],
            'cor_filename' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'status'       => ['type' => 'varchar', 'constraint' => 20, 'default' => 'pending', 'comment' => 'pending, verified, rejected'],
            'uploaded_at'  => ['type' => 'datetime', 'null' => true],
            'verified_by'  => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'verified_at'  => ['type' => 'datetime', 'null' => true],
            'created_at'   => ['type' => 'datetime', 'null' => true],
            'updated_at'   => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['reservist_id', 'year_level']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->createTable('student_enrollments', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('student_enrollments', true);
        $this->forge->dropColumn('reservists', ['reserve_classification', 'classification_updated_by', 'classification_updated_at']);
    }
}
