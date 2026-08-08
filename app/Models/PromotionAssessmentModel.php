<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PromotionAssessmentModel extends Model
{
    protected $table            = 'promotion_assessments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'reservist_id',
        'from_rank_id',
        'to_rank_id',
        'education_track_id',
        'promotion_rule_id',
        'promotion_rule_version_id',
        'as_of_date',
        'time_in_grade_months',
        'promotion_status',
        'is_eligible',
        'requires_manual_review',
        'snapshot_json',
        'assessed_by',
        'verified_by',
        'verified_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'reservist_id' => 'required|is_natural_no_zero',
        'to_rank_id'   => 'required|is_natural_no_zero',
        'as_of_date'   => 'required|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Returns the most recently created assessment for $reservistId
     * targeting $toRankId, or null when none exists yet.
     */
    public function mostRecentFor(int $reservistId, int $toRankId): ?array
    {
        return $this->where('reservist_id', $reservistId)
            ->where('to_rank_id', $toRankId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Returns every assessment on file for $reservistId, most recent
     * first, for display on the reservist profile page.
     */
    public function historyFor(int $reservistId): array
    {
        return $this->where('reservist_id', $reservistId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Counts each reservist's MOST RECENT assessment by
     * `promotion_status` (eligible / not_yet_eligible / overdue / null),
     * optionally scoped to a single organizational unit. Powers the
     * dashboard's Eligible/Overdue tiles and the Promotion Backlog
     * Report.
     *
     * @return array<string, int> keyed by promotion_status ('unverified' for null)
     */
    public function latestStatusCounts(?int $organizationalUnitId = null): array
    {
        $sql = 'SELECT pa.promotion_status, COUNT(*) AS total
                FROM promotion_assessments pa
                INNER JOIN (
                    SELECT reservist_id, MAX(id) AS max_id
                    FROM promotion_assessments
                    GROUP BY reservist_id
                ) latest ON latest.max_id = pa.id
                INNER JOIN reservists r ON r.id = pa.reservist_id';

        $bindings = [];

        if ($organizationalUnitId !== null) {
            $sql       .= ' WHERE r.organizational_unit_id = ?';
            $bindings[] = $organizationalUnitId;
        }

        $sql .= ' GROUP BY pa.promotion_status';

        $rows   = $this->db->query($sql, $bindings)->getResultArray();
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['promotion_status'] ?? 'unverified'] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * The reservists whose most recent assessment is `overdue`,
     * optionally scoped to a unit -- the Promotion Backlog Report's
     * data source.
     */
    public function overdueReservists(?int $organizationalUnitId = null): array
    {
        $sql = "SELECT pa.*, r.reservist_number, r.first_name, r.last_name, r.organizational_unit_id,
                       ou.unit_name, rk.rank_name, et.track_name
                FROM promotion_assessments pa
                INNER JOIN (
                    SELECT reservist_id, MAX(id) AS max_id
                    FROM promotion_assessments
                    GROUP BY reservist_id
                ) latest ON latest.max_id = pa.id
                INNER JOIN reservists r ON r.id = pa.reservist_id
                LEFT JOIN organizational_units ou ON ou.id = r.organizational_unit_id
                LEFT JOIN ranks rk ON rk.id = r.current_rank_id
                LEFT JOIN education_tracks et ON et.id = r.education_track_id
                WHERE pa.promotion_status = 'overdue'";

        $bindings = [];

        if ($organizationalUnitId !== null) {
            $sql       .= ' AND r.organizational_unit_id = ?';
            $bindings[] = $organizationalUnitId;
        }

        $sql .= ' ORDER BY pa.time_in_grade_months DESC';

        return $this->db->query($sql, $bindings)->getResultArray();
    }
}
