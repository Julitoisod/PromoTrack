<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class TrainingTypeModel extends Model
{
    protected $table            = 'training_types';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'training_code',
        'training_name',
        'is_mles',
        'description',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        // Not an allowed field; present only so `is_unique[...,id,{id}]`
        // below can resolve the `{id}` placeholder on updates.
        'id'            => 'permit_empty|is_natural_no_zero',
        'training_code' => 'required|max_length[30]|is_unique[training_types.training_code,id,{id}]',
        'training_name' => 'required|max_length[150]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function activeTrainingTypes(): array
    {
        return $this->where('is_active', 1)->orderBy('training_name', 'ASC')->findAll();
    }
}
