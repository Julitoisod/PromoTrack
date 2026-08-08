<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PackageStatusHistoryModel extends Model
{
    protected $table            = 'package_status_histories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'from_status',
        'to_status',
        'changed_by',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_package_id' => 'required|is_natural_no_zero',
        'to_status'            => 'required|max_length[40]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function forPackage(int $promotionPackageId): array
    {
        return $this->where('promotion_package_id', $promotionPackageId)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Average number of days a package sits in each status before moving on.
     *
     * Every transition writes a row here, so the time spent in a status is the
     * gap between the row that entered it and the next row for that package.
     * Only completed stays are counted -- a package still sitting in a status
     * has no exit row yet, and counting it as zero would flatter the average.
     *
     * @return array<string, float> status => average days, slowest first
     */
    public function averageDaysInStatus(?int $organizationalUnitId = null): array
    {
        $builder = $this->db->table('package_status_histories AS entered')
            ->select('entered.to_status AS status, AVG(TIMESTAMPDIFF(HOUR, entered.created_at, exited.created_at) / 24) AS avg_days')
            ->join(
                'package_status_histories AS exited',
                'exited.promotion_package_id = entered.promotion_package_id AND exited.id = ('
                    . ' SELECT MIN(next.id) FROM package_status_histories AS next'
                    . ' WHERE next.promotion_package_id = entered.promotion_package_id AND next.id > entered.id)',
                'inner'
            )
            ->groupBy('entered.to_status');

        if ($organizationalUnitId !== null) {
            $builder->join('promotion_packages', 'promotion_packages.id = entered.promotion_package_id')
                ->where('promotion_packages.organizational_unit_id', $organizationalUnitId);
        }

        $averages = [];

        foreach ($builder->get()->getResultArray() as $row) {
            $averages[(string) $row['status']] = round((float) $row['avg_days'], 1);
        }

        arsort($averages);

        return $averages;
    }
}
