<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UnitModel extends Model
{
    protected $table            = 'organizational_units';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'parent_unit_id',
        'unit_code',
        'unit_name',
        'unit_type',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        // Not an allowed field; present only so `is_unique[...,id,{id}]`
        // below can resolve the `{id}` placeholder on updates.
        'id'        => 'permit_empty|is_natural_no_zero',
        'unit_code' => 'required|max_length[30]|is_unique[organizational_units.unit_code,id,{id}]',
        'unit_name' => 'required|max_length[150]',
        'unit_type' => 'permit_empty|max_length[50]',
        'parent_unit_id' => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function activeUnits(): array
    {
        return $this->where('is_active', 1)->orderBy('unit_name', 'ASC')->findAll();
    }
}
