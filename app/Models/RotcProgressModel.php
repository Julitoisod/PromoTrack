<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * One row per student reservist per E-level stage (see
 * Config\RotcProgression). `checks` is a JSON list of the ticked checkbox
 * keys; the model decodes it so callers always get a plain list.
 */
class RotcProgressModel extends Model
{
    protected $table          = 'rotc_progress';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'reservist_id',
        'stage',
        'classification',
        'training_result',
        'recommendation',
        'pose_rank',
        'checks',
        'justification_path',
        'justification_filename',
        'updated_by',
    ];

    protected $useTimestamps = true;

    protected $validationRules = [
        'reservist_id'    => 'required|is_natural_no_zero',
        'stage'           => 'required|in_list[E1,E2,E3,E4,E5,E6,E7,E8]',
        'classification'  => 'permit_empty|in_list[standby,ready_reserve]',
        'training_result' => 'permit_empty|in_list[complete,incomplete]',
        'recommendation'  => 'permit_empty|in_list[recommended,not_recommended]',
        'pose_rank'       => 'permit_empty|max_length[30]',
    ];

    /**
     * Every stage row for $reservistId, keyed by stage code, with `checks`
     * decoded to a list of keys.
     *
     * @return array<string, array<string, mixed>>
     */
    public function stagesFor(int $reservistId): array
    {
        $rows = [];

        foreach ($this->where('reservist_id', $reservistId)->findAll() as $row) {
            $row['checks']       = self::decodeChecks($row['checks'] ?? null);
            $rows[$row['stage']] = $row;
        }

        return $rows;
    }

    /**
     * Inserts or updates the $stage row of $reservistId. Returns false with
     * the model's errors on a validation failure.
     *
     * @param array<string, mixed> $data
     */
    public function saveStage(int $reservistId, string $stage, array $data): bool
    {
        if (array_key_exists('checks', $data) && is_array($data['checks'])) {
            $data['checks'] = json_encode(array_values($data['checks']));
        }

        $existing = $this->where('reservist_id', $reservistId)->where('stage', $stage)->first();
        $data     = ['reservist_id' => $reservistId, 'stage' => $stage] + $data;

        return $existing === null
            ? $this->insert($data) !== false
            : $this->update($existing['id'], $data);
    }

    /**
     * @return list<string>
     */
    public static function decodeChecks(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
