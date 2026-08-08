<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PromotionPackageModel extends Model
{
    protected $table            = 'promotion_packages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'reservist_id',
        'promotion_assessment_id',
        'organizational_unit_id',
        'package_number',
        'status',
        'current_routing_level',
        'completeness_percentage',
        'open_marker',
        'submitted_by',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'reservist_id'           => 'required|is_natural_no_zero',
        'organizational_unit_id' => 'required|is_natural_no_zero',
        'package_number'         => 'required|max_length[40]|is_unique[promotion_packages.package_number,id,{id}]',
    ];

    protected $validationMessages = [
        'package_number' => [
            'is_unique' => 'This package number is already in use.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * True when $reservistId already has an open (non-terminal) package,
     * i.e. `open_marker` is set on one of its rows. Used to block
     * creating a second concurrent package for the same reservist.
     */
    public function hasOpenPackage(int $reservistId): bool
    {
        return $this->where('reservist_id', $reservistId)
            ->where('open_marker', 1)
            ->countAllResults() > 0;
    }

    /**
     * Packages visible to a unit-scoped user, optionally further
     * restricted to a single workflow "queue" (e.g. all packages
     * currently `under_rcdg_review`).
     */
    public function listForDisplay(?int $organizationalUnitId, ?array $statuses = null): array
    {
        $builder = $this->select(
            'promotion_packages.*, reservists.first_name, reservists.last_name, reservists.reservist_number,'
            . ' organizational_units.unit_name'
        )
            ->join('reservists', 'reservists.id = promotion_packages.reservist_id')
            ->join('organizational_units', 'organizational_units.id = promotion_packages.organizational_unit_id', 'left');

        if ($organizationalUnitId !== null) {
            $builder->where('promotion_packages.organizational_unit_id', $organizationalUnitId);
        }

        if ($statuses !== null) {
            $builder->whereIn('promotion_packages.status', $statuses);
        }

        return $builder->orderBy('promotion_packages.updated_at', 'DESC')->findAll();
    }

    /**
     * Counts packages by status, optionally scoped to a unit. Powers
     * the dashboard's package-pipeline widget.
     *
     * @return array<string, int>
     */
    public function countsByStatus(?int $organizationalUnitId = null): array
    {
        $builder = $this->select('status, COUNT(*) AS total');

        if ($organizationalUnitId !== null) {
            $builder->where('organizational_unit_id', $organizationalUnitId);
        }

        $rows   = $builder->groupBy('status')->findAll();
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Generates the next sequential package number for $year
     * (defaults to the current year), formatted `PKG-{year}-{seq}`.
     */
    public function nextPackageNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');
        $prefix = "PKG-{$year}-";

        $count = $this->like('package_number', $prefix, 'after')->countAllResults();

        return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
