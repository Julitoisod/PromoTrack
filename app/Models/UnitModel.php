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

    /**
     * A company (Alpha, Bravo, ...) is an organizational unit like any
     * other -- it is `unit_type` that tells the two apart, so the same
     * Reference Data screen maintains both and each parent unit keeps
     * its own set of companies.
     */
    public const COMPANY_UNIT_TYPE = 'company';

    /**
     * The units a reservist or user account can be assigned to.
     * Companies are excluded: they are picked separately into
     * `reservists.company_unit_id`, and a company in this list would let
     * someone file a reservist under "Alpha Company" as their whole
     * unit, which is what every unit-scoped query keys on.
     */
    public function activeUnits(): array
    {
        return $this->where('is_active', 1)
            ->groupStart()
            ->where('unit_type !=', self::COMPANY_UNIT_TYPE)
            ->orWhere('unit_type', null)
            ->groupEnd()
            ->orderBy('unit_name', 'ASC')
            ->findAll();
    }

    /**
     * The companies offered in the company picker, each carrying its
     * parent unit's name so "Alpha Company" is unambiguous when several
     * units each have one.
     */
    public function activeCompanies(): array
    {
        return $this->select('organizational_units.*, parent_units.unit_name AS parent_unit_name')
            ->join('organizational_units AS parent_units', 'parent_units.id = organizational_units.parent_unit_id', 'left')
            ->where('organizational_units.is_active', 1)
            ->where('organizational_units.unit_type', self::COMPANY_UNIT_TYPE)
            ->orderBy('parent_units.unit_name', 'ASC')
            ->orderBy('organizational_units.unit_name', 'ASC')
            ->findAll();
    }
}
