<?php

namespace App\Services;

use App\Models\Control;
use App\Models\GuidedQuestionBank;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GuidedQuestionBankService
{
    public function coverage(GuidedQuestionBank $bank): array
    {
        app(GuidedFeatureGate::class)->assertEnabled();
        $questions = $bank->questions()->with('targets')->get();
        $requirements = Requirement::query()->where('standard_id', $bank->standard_id)
            ->where('is_mandatory', true)
            ->when($this->isIso27001($bank), fn ($q) => $q->whereNotIn('code', ['4', '5', '6', '7', '8', '9', '10']))
            ->get(['id', 'code', 'title']);
        $controls = Control::query()->where('standard_id', $bank->standard_id)
            ->where('is_group', false)->get(['id', 'code', 'title']);
        $mappedRequirements = $questions->flatMap(fn ($q) => $q->targets->pluck('requirement_id'))->filter()->unique();
        $mappedControls = $questions->flatMap(fn ($q) => $q->targets->pluck('control_id'))->filter()->unique();
        $targetKeys = $questions->flatMap(fn ($q) => $q->targets->map(
            fn ($t) => $t->control_id ? 'control:'.$t->control_id : 'requirement:'.$t->requirement_id
        ));
        $duplicateTargets = $targetKeys->countBy()->filter(fn ($count) => $count > 1)->keys()->values()->all();
        $catalogGaps = $this->catalogGaps($bank, $requirements, $controls);
        $codes = $questions->pluck('code')->all();
        $branchDestinations = $questions->flatMap(fn ($question) =>
            collect($question->branch_rules ?? [])->pluck('next_code'))->filter()->unique();
        $brokenBranches = [];
        $invalidQuestions = [];

        foreach ($questions as $question) {
            // Branch-only questions gather clarification; each required ISO
            // criterion must be reachable independently of an optional branch.
            $isFollowup = $branchDestinations->contains($question->code);
            if (($question->targets->isEmpty() && ! $isFollowup)
                || ($isFollowup && $question->targets->isNotEmpty())) {
                $invalidQuestions[] = $question->code;
            }
            $options = $question->answer_options ?? [];
            if ($question->answer_type === 'single_choice' && (! is_array($options) || count($options) === 0)) {
                $invalidQuestions[] = $question->code;
            }
            foreach ($question->branch_rules ?? [] as $rule) {
                if (! is_array($rule) || ! isset($rule['next_code']) || ! in_array($rule['next_code'], $codes, true)
                    || ! isset($rule['when']) || ($question->answer_type === 'single_choice'
                        && ! in_array((string) $rule['when'], array_map('strval', $options), true))) {
                    $brokenBranches[] = $question->code;
                }
            }
        }
        $graph = $questions->mapWithKeys(fn ($q) => [$q->code => collect($q->branch_rules ?? [])
            ->pluck('next_code')->filter()->all()])->all();
        $visiting = [];
        $visited = [];
        $checkCycle = function (string $code) use (&$checkCycle, &$visiting, &$visited, &$brokenBranches, $graph): void {
            if (isset($visiting[$code])) {
                $brokenBranches[] = $code;
                return;
            }
            if (isset($visited[$code])) { return; }
            $visiting[$code] = true;
            foreach ($graph[$code] ?? [] as $next) {
                if (isset($graph[$next])) { $checkCycle($next); }
            }
            unset($visiting[$code]);
            $visited[$code] = true;
        };
        foreach (array_keys($graph) as $code) { $checkCycle($code); }

        return [
            'question_count' => $questions->count(),
            'requirements_total' => $requirements->count(),
            'requirements_covered' => $mappedRequirements->intersect($requirements->pluck('id'))->count(),
            'missing_requirements' => $requirements->whereNotIn('id', $mappedRequirements)->pluck('code')->values()->all(),
            'controls_total' => $controls->count(),
            'controls_covered' => $mappedControls->intersect($controls->pluck('id'))->count(),
            'missing_controls' => $controls->whereNotIn('id', $mappedControls)->pluck('code')->values()->all(),
            'broken_branches' => array_values(array_unique($brokenBranches)),
            'invalid_questions' => array_values(array_unique($invalidQuestions)),
            'duplicate_targets' => $duplicateTargets,
            'catalog_missing_requirements' => $catalogGaps['requirements'],
            'catalog_missing_controls' => $catalogGaps['controls'],
            'catalog_warning' => 'Cakupan dan kecukupan katalog diverifikasi terhadap kode standar; isi dan pernyataan standar harus tetap diperiksa terhadap sumber berlisensi.',
        ];
    }

    private function isIso27001(GuidedQuestionBank $bank): bool
    {
        $standard = $bank->standard;
        return $standard !== null
            && strtoupper(trim($standard->code)) === 'ISO/IEC 27001'
            && str_starts_with((string) $standard->edition, '2022');
    }

    /**
     * ISO/IEC 27001:2022 catalog identity checks use public clause/control
     * identifiers, never copyrighted normative wording or example-org data.
     */
    private function catalogGaps(GuidedQuestionBank $bank, $requirements, $controls): array
    {
        if (! $this->isIso27001($bank)) {
            return ['requirements' => [], 'controls' => []];
        }

        $clauseCodes = [
            '4.1','4.2','4.3','4.4', '5.1','5.2','5.3',
            '6.1.1','6.1.2','6.1.3','6.2','6.3',
            '7.1','7.2','7.3','7.4','7.5.1','7.5.2','7.5.3',
            '8.1','8.2','8.3', '9.1','9.2.1','9.2.2','9.3.1','9.3.2','9.3.3',
            '10.1','10.2',
        ];
        $annex = [];
        foreach ([5 => 37, 6 => 8, 7 => 14, 8 => 34] as $group => $count) {
            for ($number = 1; $number <= $count; $number++) {
                $annex[] = 'A.'.$group.'.'.$number;
            }
        }
        return [
            'requirements' => array_values(array_diff($clauseCodes, $requirements->pluck('code')->all())),
            'controls' => array_values(array_diff($annex, $controls->pluck('code')->all())),
        ];
    }

    /**
     * Generate editable starter prompts from the catalog already owned/licensed by
     * the installing organization; no organization or ISO wording is embedded.
     */
    public function generateStarterQuestions(GuidedQuestionBank $bank, User $actor): int
    {
        app(GuidedFeatureGate::class)->assertEnabled();
        if (! $actor->can('guided.bank.manage') || $bank->status !== 'draft') {
            throw ValidationException::withMessages(['bank' => 'Hanya bank draft yang berwenang dapat diisi otomatis.']);
        }
        $options = ['Ya, dilaksanakan', 'Sebagian', 'Sedang direncanakan', 'Belum', 'Tidak mengetahui'];
        $count = 0;
        $requirements = Requirement::query()->where('standard_id', $bank->standard_id)
            ->where('is_mandatory', true)
            ->when($this->isIso27001($bank), fn ($q) => $q->whereNotIn('code', ['4', '5', '6', '7', '8', '9', '10']))
            ->orderBy('sort_order')->get();
        foreach ($requirements as $requirement) {
            $question = $bank->questions()->firstOrCreate(['code' => 'REQ-'.$requirement->code], [
                'prompt' => 'Bagaimana persyaratan '.$requirement->code.' - '.$requirement->title.' dipenuhi dalam lingkup organisasi?',
                'help_text' => 'Ceritakan kondisi nyata, sebutkan cakupan dan lampirkan bukti yang tersedia.',
                'answer_type' => 'single_choice', 'answer_options' => $options, 'is_required' => true,
                'sort_order' => $requirement->sort_order,
            ]);
            $question->targets()->firstOrCreate(['requirement_id' => $requirement->id], [
                'criterion' => 'Pelaksanaan persyaratan '.$requirement->code.' dan bukti pendukungnya',
                'evidence_profile' => 'standard', 'is_required' => true,
            ]);
            $count++;
        }
        $controls = Control::query()->where('standard_id', $bank->standard_id)
            ->where('is_group', false)->orderBy('sort_order')->get();
        foreach ($controls as $control) {
            $question = $bank->questions()->firstOrCreate(['code' => 'CTRL-'.$control->code], [
                'prompt' => 'Bagaimana kontrol '.$control->code.' - '.$control->title.' diterapkan dalam lingkup organisasi?',
                'help_text' => 'Jelaskan penerapan, pengecualian yang diajukan, aset atau proses yang terkait, serta bukti yang tersedia.',
                'answer_type' => 'single_choice', 'answer_options' => $options, 'is_required' => true,
                'sort_order' => $control->sort_order,
            ]);
            $question->targets()->firstOrCreate(['control_id' => $control->id], [
                'criterion' => 'Penerapan kontrol '.$control->code.' terhadap ruang lingkup yang relevan',
                'evidence_profile' => 'standard', 'is_required' => true,
            ]);
            $count++;
        }
        return $count;
    }

    public function publish(GuidedQuestionBank $bank, User $actor): array
    {
        app(GuidedFeatureGate::class)->assertEnabled();
        if (! $actor->can('guided.bank.manage')) {
            throw ValidationException::withMessages(['authorization' => 'Tidak berwenang menerbitkan bank pertanyaan.']);
        }
        if ($bank->status !== 'draft' || $bank->questions()->count() === 0) {
            throw ValidationException::withMessages(['bank' => 'Hanya bank draft berisi pertanyaan yang bisa diterbitkan.']);
        }
        $coverage = $this->coverage($bank);
        if ($coverage['missing_requirements'] || $coverage['missing_controls']
            || $coverage['broken_branches'] || $coverage['invalid_questions']
            || $coverage['catalog_missing_requirements'] || $coverage['catalog_missing_controls']
            || $coverage['duplicate_targets']) {
            throw ValidationException::withMessages(['coverage' => 'Bank belum lengkap; periksa persyaratan, kontrol, percabangan, dan pilihan jawaban.']);
        }
        $bank->update(['status' => 'published']);
        return $coverage;
    }
}
