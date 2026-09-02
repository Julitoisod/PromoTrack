<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EducationTrackModel;
use App\Models\PromotionRuleModel;
use App\Models\PromotionRuleVersionModel;
use App\Models\RankModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Authoring surface for the versioned promotion rules.
 *
 * Every threshold these rules carry -- time in grade, MLES, fitness, extra
 * prerequisites -- is flagged in the design document as a placeholder pending
 * official AFP/Philippine Army validation, which means they *will* change.
 * Until now they could only change by SQL, and a direct UPDATE would silently
 * rewrite the basis of every assessment already computed.
 *
 * So a threshold change here is never an edit. `storeVersion()` supersedes:
 * it expires the version currently in force and inserts a new one with the
 * next version number, leaving the old row intact and still resolvable for
 * any assessment that cited it. That is what makes a past assessment
 * explainable after the rules move on.
 */
class PromotionRulesController extends BaseController
{
    private PromotionRuleModel $ruleModel;
    private PromotionRuleVersionModel $versionModel;
    private RankModel $rankModel;
    private EducationTrackModel $educationTrackModel;

    public function __construct()
    {
        $this->ruleModel           = new PromotionRuleModel();
        $this->versionModel        = new PromotionRuleVersionModel();
        $this->rankModel           = new RankModel();
        $this->educationTrackModel = new EducationTrackModel();
    }

    /**
     * Lists every rank-transition rule with its currently effective version.
     */
    public function index(): string
    {
        $rules = $this->ruleModel->orderBy('rule_name', 'ASC')->findAll();
        $ranks = $this->rankModel->findAll();

        $rankNames = [];

        foreach ($ranks as $rank) {
            $rankNames[(int) $rank['id']] = $rank['rank_name'];
        }

        foreach ($rules as $index => $rule) {
            $rules[$index]['from_rank_name'] = $rankNames[(int) $rule['from_rank_id']] ?? '—';
            $rules[$index]['to_rank_name']   = $rankNames[(int) $rule['to_rank_id']] ?? '—';

            // resolveEffectiveVersion() with no track returns only the
            // all-tracks version, so a rule versioned per education track
            // would otherwise read as having no version at all. Count them
            // separately and let the view say which case it is.
            $rules[$index]['effective']     = $this->versionModel->resolveEffectiveVersion((int) $rule['id']);
            $rules[$index]['version_count'] = (new PromotionRuleVersionModel())
                ->where('promotion_rule_id', $rule['id'])
                ->countAllResults();
        }

        return view('admin/promotion_rules/index', [
            'title'       => 'Promotion Rules',
            'rules' => $rules,
            'ranks' => $this->rankModel->activeRanksOrdered(),
        ]);
    }

    /**
     * Shows one rule and its full version history.
     */
    public function show(?int $id = null): string
    {
        $rule = $this->findRuleOrFail($id);

        $versions = $this->versionModel
            ->where('promotion_rule_id', $rule['id'])
            ->orderBy('version_number', 'DESC')
            ->findAll();

        return view('admin/promotion_rules/show', [
            'title'       => 'Promotion Rule',
            'rule'             => $rule,
            'versions'         => $versions,
            'effective'        => $this->versionModel->resolveEffectiveVersion((int) $rule['id']),
            'educationTracks'  => $this->educationTrackModel->activeTracks(),
            'ranks'            => $this->rankModel->findAll(),
        ]);
    }

    /**
     * Creates a rank-transition rule. Thresholds are not set here: a rule
     * carries none of its own, they all live in its versions.
     */
    public function store(): RedirectResponse
    {
        $data = [
            'from_rank_id' => $this->request->getPost('from_rank_id') ?: null,
            'to_rank_id'   => $this->request->getPost('to_rank_id') ?: null,
            'rule_name'    => $this->request->getPost('rule_name') ?: null,
            'is_active'    => 1,
        ];

        $user = $this->currentUser();

        if ($user !== null) {
            $data['created_by'] = (int) $user->id;
        }

        if (! $this->ruleModel->insert($data)) {
            return redirect()->back()->withInput()
                ->with('errors', $this->ruleModel->errors())
                ->with('error', 'The promotion rule could not be created. Please review the errors below.');
        }

        return redirect()->to(site_url('admin/rules/' . $this->ruleModel->getInsertID()))
            ->with('success', 'Promotion rule created. Add its first version to set the thresholds.');
    }

    /**
     * Supersedes the rule's current version with a new one.
     *
     * The previous version is expired rather than overwritten, so every
     * assessment that cited it can still be explained against the thresholds
     * that were actually in force when it ran.
     */
    public function storeVersion(?int $ruleId = null): RedirectResponse
    {
        $rule = $this->findRuleOrFail($ruleId);

        $effectiveDate = (string) ($this->request->getPost('effective_date') ?: date('Y-m-d'));

        // Request values arrive as strings; the track id is compared
        // numerically below and passed to a typed parameter.
        $educationTrackId = $this->request->getPost('education_track_id');
        $educationTrackId = ($educationTrackId === null || $educationTrackId === '') ? null : (int) $educationTrackId;

        $data = [
            'promotion_rule_id'              => $rule['id'],
            'education_track_id'             => $educationTrackId,
            'version_number'                 => $this->nextVersionNumber((int) $rule['id']),
            'minimum_time_in_grade_months'   => $this->request->getPost('minimum_time_in_grade_months') ?: null,
            'requires_mles'                  => $this->request->getPost('requires_mles') !== null ? 1 : 0,
            'requires_physical_fitness_test' => $this->request->getPost('requires_physical_fitness_test') !== null ? 1 : 0,
            'additional_prerequisites'       => $this->request->getPost('additional_prerequisites') ?: null,
            'effective_date'                 => $effectiveDate,
            'expiry_date'                    => $this->request->getPost('expiry_date') ?: null,
            'is_active'                      => 1,
            'notes'                          => $this->request->getPost('notes') ?: null,
        ];

        $user = $this->currentUser();

        if ($user !== null) {
            $data['created_by'] = (int) $user->id;
        }

        $db = db_connect();
        $db->transStart();

        $superseded = $this->expireCurrentVersion($rule, $educationTrackId, $effectiveDate);

        if (! $this->versionModel->insert($data)) {
            $db->transRollback();

            return redirect()->back()->withInput()
                ->with('errors', $this->versionModel->errors())
                ->with('error', 'The rule version could not be saved. Please review the errors below.');
        }

        $db->transComplete();

        return redirect()->to(site_url('admin/rules/' . $rule['id']))
            ->with('success', $superseded
                ? 'New rule version saved. The previous version was expired and kept on record.'
                : 'First rule version saved.');
    }

    /**
     * Expires the version currently in force for the same education track,
     * the day before the incoming version takes effect. Returns true when a
     * version was actually superseded.
     */
    private function expireCurrentVersion(array $rule, ?int $educationTrackId, string $effectiveDate): bool
    {
        $current = $this->versionModel->resolveEffectiveVersion(
            (int) $rule['id'],
            $effectiveDate,
            $educationTrackId
        );

        // resolveEffectiveVersion() falls back to a track-agnostic version, so
        // check that what came back is really the row this change supersedes.
        if ($current === null
            || (int) ($current['education_track_id'] ?? 0) !== (int) ($educationTrackId ?? 0)
        ) {
            return false;
        }

        $expiresOn = date('Y-m-d', strtotime($effectiveDate . ' -1 day'));

        $this->versionModel->update((int) $current['id'], ['expiry_date' => $expiresOn]);

        return true;
    }

    /**
     * Flips a rule between active and inactive. Inactive rules stop governing
     * new assessments but stay on file with their versions.
     */
    public function toggle(?int $id = null): RedirectResponse
    {
        $rule      = $this->findRuleOrFail($id);
        $nowActive = ((int) $rule['is_active']) === 1 ? 0 : 1;

        $this->ruleModel->update((int) $rule['id'], ['is_active' => $nowActive]);

        return redirect()->to(site_url('admin/rules'))
            ->with('success', 'Promotion rule ' . ($nowActive === 1 ? 'reactivated' : 'deactivated') . '.');
    }

    private function nextVersionNumber(int $ruleId): int
    {
        $latest = $this->versionModel
            ->selectMax('version_number', 'highest')
            ->where('promotion_rule_id', $ruleId)
            ->first();

        return (int) ($latest['highest'] ?? 0) + 1;
    }

    private function findRuleOrFail(?int $id): array
    {
        $rule = $id === null ? null : $this->ruleModel->find($id);

        if ($rule === null) {
            throw PageNotFoundException::forPageNotFound('That promotion rule was not found.');
        }

        return $rule;
    }
}
