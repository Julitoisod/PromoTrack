<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the single, rank-agnostic thirteen-item promotion document
 * checklist (Items A-M, Table 4 of the PromoTrack AFP-RF thesis,
 * Chapter 1) as the default `document_requirement_templates` /
 * `document_requirements` rows every new promotion package draws from.
 */
class DocumentRequirementsSeeder extends Seeder
{
    /**
     * @var list<array{code: string, label: string, conditional: bool, condition: string|null, override: bool}>
     */
    private const ITEMS = [
        ['code' => 'A', 'label' => 'Recommendation for Promotion from Group Commander', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'B', 'label' => 'Updated Reservist Summary of Information (RSOI)', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'C', 'label' => 'Post Card Size Picture, In Proper Uniform, White Background', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'D', 'label' => 'Enlistment Order', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'E', 'label' => 'All Promotion Orders', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'F', 'label' => 'Unit Assignment and Designation Orders (based on TO)', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'G', 'label' => 'All ADT Orders with Present Rank, Authenticated', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'H', 'label' => 'Educational Documents, Diploma or TOR', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'I', 'label' => 'Awards and Decorations', 'conditional' => false, 'condition' => null, 'override' => true],
        ['code' => 'J', 'label' => 'PFT Result from RCDG', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'K', 'label' => 'PRC or CSC Documents, for Licensed Professional', 'conditional' => true, 'condition' => 'licensed_professional_only', 'override' => false],
        ['code' => 'L', 'label' => 'None Pending Case Certificate, RCDG Level', 'conditional' => false, 'condition' => null, 'override' => false],
        ['code' => 'M', 'label' => 'Summary of Accomplishment', 'conditional' => false, 'condition' => null, 'override' => false],
    ];

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existingTemplate = $this->db->table('document_requirement_templates')
            ->where('promotion_rule_id', null)
            ->where('template_name', 'AFP Enlisted Promotion Package (Items A-M)')
            ->get()
            ->getFirstRow('array');

        if ($existingTemplate !== null) {
            return;
        }

        $this->db->table('document_requirement_templates')->insert([
            'promotion_rule_id' => null,
            'template_name'     => 'AFP Enlisted Promotion Package (Items A-M)',
            'version'           => 1,
            'is_active'         => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $templateId = $this->db->insertID();

        foreach (self::ITEMS as $index => $item) {
            $this->db->table('document_requirements')->insert([
                'document_requirement_template_id' => $templateId,
                'document_code'                     => $item['code'],
                'document_label'                    => $item['label'],
                'is_conditional'                    => $item['conditional'] ? 1 : 0,
                'condition_expression'              => $item['condition'],
                'allows_commander_override'          => $item['override'] ? 1 : 0,
                'display_order'                      => $index + 1,
                'created_at'                          => $now,
                'updated_at'                          => $now,
            ]);
        }
    }
}
