<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EducationTrackModel extends Model
{
    protected $table            = 'education_tracks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'track_code',
        'track_name',
        'description',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        // Not an allowed field; present only so `is_unique[...,id,{id}]`
        // below can resolve the `{id}` placeholder on updates.
        'id'         => 'permit_empty|is_natural_no_zero',
        'track_code' => 'required|max_length[30]|is_unique[education_tracks.track_code,id,{id}]',
        'track_name' => 'required|max_length[150]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function activeTracks(): array
    {
        return $this->where('is_active', 1)->orderBy('track_name', 'ASC')->findAll();
    }
}
