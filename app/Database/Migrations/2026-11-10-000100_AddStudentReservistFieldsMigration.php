<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Client revision of 2026-11-09 ("Sign up.docx" and the student reservist
 * dashboard mock-up):
 *
 *  - `reservists.educational_attainment` + `degree_details`: the sign-up form
 *    now asks for attainment (None / Undergraduate / Bachelor's / Masteral /
 *    Doctorate / Others) and, for a degree, which one.
 *  - `reservists.profile_photo`: the dashboard header shows the reservist's
 *    own photo. Stored under writable/, never public/.
 *  - `rotc_progress`: one row per E-level stage (E1-E8) of a student
 *    reservist, holding the buttons the client listed for that stage.
 *
 * Reference rows the new form selects from are inserted here rather than in a
 * seeder, because seeders are not run against a live database: the ROTC
 * cadet levels MS31-MS42, the five CDCs 1501-1505, and each CDC's Alpha-Echo
 * Ready Reserve Infantry Battalion companies. Every insert is skipped when
 * the code already exists, so the migration is safe on a database where an
 * administrator has already added some of them by hand.
 */
class AddStudentReservistFieldsMigration extends Migration
{
    private const ROTC_RANKS = [
        ['rank_code' => 'MS31', 'rank_name' => 'MS31', 'sort_order' => 1],
        ['rank_code' => 'MS32', 'rank_name' => 'MS32', 'sort_order' => 2],
        ['rank_code' => 'MS41', 'rank_name' => 'MS41', 'sort_order' => 3],
        ['rank_code' => 'MS42', 'rank_name' => 'MS42', 'sort_order' => 4],
    ];

    private const CDCS = ['1501', '1502', '1503', '1504', '1505'];

    private const COMPANIES = ['A' => 'Alpha', 'B' => 'Bravo', 'C' => 'Charlie', 'D' => 'Delta', 'E' => 'Echo'];

    public function up(): void
    {
        $this->forge->addColumn('reservists', [
            'educational_attainment' => [
                'type'       => 'varchar',
                'constraint' => 30,
                'null'       => true,
                'after'      => 'education_track_id',
                'comment'    => 'none, undergraduate, bachelor, masteral, doctorate, others',
            ],
            'degree_details' => [
                'type'       => 'varchar',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'educational_attainment',
                'comment'    => 'Which degree, or what "others" means',
            ],
            'profile_photo' => [
                'type'       => 'varchar',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'address',
                'comment'    => 'File name under writable/uploads/profile_photos',
            ],
        ]);

        $this->forge->addField([
            'id'                     => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'reservist_id'           => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true],
            'stage'                  => ['type' => 'varchar', 'constraint' => 5, 'comment' => 'E1-E8'],
            'classification'         => ['type' => 'varchar', 'constraint' => 20, 'null' => true, 'comment' => 'standby, ready_reserve'],
            'training_result'        => ['type' => 'varchar', 'constraint' => 20, 'null' => true, 'comment' => 'complete, incomplete'],
            'recommendation'         => ['type' => 'varchar', 'constraint' => 20, 'null' => true, 'comment' => 'recommended, not_recommended'],
            'pose_rank'              => ['type' => 'varchar', 'constraint' => 30, 'null' => true],
            'checks'                 => ['type' => 'text', 'null' => true, 'comment' => 'JSON list of ticked checkbox keys'],
            'justification_path'     => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'justification_filename' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'updated_by'             => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'             => ['type' => 'datetime', 'null' => true],
            'updated_at'             => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['reservist_id', 'stage']);
        $this->forge->addForeignKey('reservist_id', 'reservists', 'id', '', 'CASCADE');
        $this->forge->createTable('rotc_progress', false, ['ENGINE' => 'InnoDB']);

        $this->insertReferenceRows();
    }

    public function down(): void
    {
        $this->forge->dropTable('rotc_progress', true);
        $this->forge->dropColumn('reservists', ['educational_attainment', 'degree_details', 'profile_photo']);

        // Reference rows stay: reservist rows may already point at them.
    }

    private function insertReferenceRows(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (self::ROTC_RANKS as $rank) {
            if ($this->exists('ranks', 'rank_code', $rank['rank_code'])) {
                continue;
            }

            $this->db->table('ranks')->insert($rank + [
                'rank_category' => 'rotc',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $rcdg = $this->db->table('organizational_units')->where('unit_type', 'rcdg')
            ->orderBy('id', 'ASC')->get()->getRowArray();

        foreach (self::CDCS as $number) {
            $cdcCode = 'CDC-' . $number;
            $cdc     = $this->db->table('organizational_units')->where('unit_code', $cdcCode)->get()->getRowArray();

            if ($cdc === null) {
                $this->db->table('organizational_units')->insert([
                    'parent_unit_id' => $rcdg['id'] ?? null,
                    'unit_code'      => $cdcCode,
                    'unit_name'      => $number . ' CDC',
                    'unit_type'      => 'cdc',
                    'is_active'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $cdcId = (int) $this->db->insertID();
            } else {
                $cdcId = (int) $cdc['id'];
            }

            foreach (self::COMPANIES as $letter => $company) {
                $companyCode = $cdcCode . '-' . $letter;

                if ($this->exists('organizational_units', 'unit_code', $companyCode)) {
                    continue;
                }

                $this->db->table('organizational_units')->insert([
                    'parent_unit_id' => $cdcId,
                    'unit_code'      => $companyCode,
                    'unit_name'      => $company . ' Company, Ready Reserve Infantry Battalion',
                    'unit_type'      => 'company',
                    'is_active'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }

    private function exists(string $table, string $column, string $value): bool
    {
        return $this->db->table($table)->where($column, $value)->countAllResults() > 0;
    }
}
