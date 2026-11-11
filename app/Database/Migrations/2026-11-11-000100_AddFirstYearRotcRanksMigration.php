<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sign-up now offers 1st Year 1st Sem and 1st Year 2nd Sem (2026-11-11), so
 * the MS1 and MS2 enrollment folders can fill. A 1st Year cadet needs a rank
 * to pick under the ROTC category: MS1 and MS2 are added here, and the ROTC
 * order is renumbered so they come before MS31-MS42.
 *
 * Every rank stays below the enlisted ranks (sort_order 10 and up), so the
 * promotion direction between real ranks is untouched. An insert is skipped
 * when the code already exists.
 */
class AddFirstYearRotcRanksMigration extends Migration
{
    private const ORDER = ['MS1' => 1, 'MS2' => 2, 'MS31' => 3, 'MS32' => 4, 'MS41' => 5, 'MS42' => 6];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (self::ORDER as $code => $order) {
            $existing = $this->db->table('ranks')->where('rank_code', $code)->get()->getRowArray();

            if ($existing === null) {
                if (in_array($code, ['MS1', 'MS2'], true)) {
                    $this->db->table('ranks')->insert([
                        'rank_code'     => $code,
                        'rank_name'     => $code,
                        'rank_category' => 'rotc',
                        'sort_order'    => $order,
                        'is_active'     => 1,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }

                continue;
            }

            if ($existing['rank_category'] === 'rotc') {
                $this->db->table('ranks')->where('id', $existing['id'])
                    ->update(['sort_order' => $order, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // MS1 and MS2 stay: reservist rows may already point at them.
        $now = date('Y-m-d H:i:s');

        foreach (['MS31' => 1, 'MS32' => 2, 'MS41' => 3, 'MS42' => 4] as $code => $order) {
            $this->db->table('ranks')->where('rank_code', $code)->where('rank_category', 'rotc')
                ->update(['sort_order' => $order, 'updated_at' => $now]);
        }
    }
}
