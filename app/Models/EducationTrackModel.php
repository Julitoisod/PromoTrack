<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
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

    /**
     * The tracks the MLES matrix actually distinguishes: those with an
     * active promotion rule version of their own (General, Completed 1st
     * Year College, ROTC Professional as seeded). Any other track is
     * assessed on the track-agnostic baseline, which is the General
     * figure -- so offering it at sign-up would let a reservist pick
     * "College Graduate" and silently land on the longest threshold.
     */
    public function mlesTracks(): array
    {
        return $this->where('is_active', 1)
            ->whereIn('id', static fn (BaseBuilder $builder) => $builder
                ->select('education_track_id')
                ->from('promotion_rule_versions')
                ->where('is_active', 1)
                ->where('education_track_id IS NOT NULL'))
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
