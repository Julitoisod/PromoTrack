<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PromotionOrderModel extends Model
{
    protected $table            = 'promotion_orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'reservist_id',
        'order_number',
        'order_date',
        'effective_date',
        'new_rank_id',
        'recorded_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_package_id' => 'required|is_natural_no_zero|is_unique[promotion_orders.promotion_package_id,id,{id}]',
        'reservist_id'         => 'required|is_natural_no_zero',
        'order_number'         => 'required|max_length[100]|is_unique[promotion_orders.order_number,id,{id}]',
        'order_date'           => 'required|valid_date',
        'effective_date'       => 'required|valid_date',
        'new_rank_id'          => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'order_number' => [
            'is_unique' => 'This promotion order number is already in use.',
        ],
        'promotion_package_id' => [
            'is_unique' => 'A promotion order has already been recorded for this package.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Counts promotion orders recorded, optionally scoped to a single
     * organizational unit (joined via the reservist). Powers the
     * dashboard's "Promotions Issued" tile.
     */
    public function countForUnit(?int $organizationalUnitId = null): int
    {
        $builder = $this->builder();

        if ($organizationalUnitId !== null) {
            $builder->join('reservists', 'reservists.id = promotion_orders.reservist_id')
                ->where('reservists.organizational_unit_id', $organizationalUnitId);
        }

        return $builder->countAllResults();
    }

    /**
     * Generates the next sequential order number for $year (defaults to
     * the current year), formatted `PO-{year}-{seq}`. Exact AFP
     * promotion-order numbering scheme REQUIRES OFFICIAL AFP/PHILIPPINE
     * ARMY VALIDATION; this is a structural placeholder.
     */
    public function nextOrderNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');
        $prefix = "PO-{$year}-";

        $count = $this->like('order_number', $prefix, 'after')->countAllResults();

        return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
