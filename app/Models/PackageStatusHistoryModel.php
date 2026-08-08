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
}
