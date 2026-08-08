<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PackageReviewModel extends Model
{
    protected $table            = 'package_reviews';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'promotion_package_id',
        'reviewer_id',
        'review_stage',
        'decision',
        'remarks',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'promotion_package_id' => 'required|is_natural_no_zero',
        'review_stage'         => 'required|in_list[cdc,rcdg]',
        'decision'             => 'required|in_list[validated,returned,endorsed]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function forPackage(int $promotionPackageId): array
    {
        return $this->where('promotion_package_id', $promotionPackageId)
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }
}
