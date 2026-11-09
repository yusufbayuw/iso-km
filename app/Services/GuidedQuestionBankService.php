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
        $questions = $bank->questions()->with('targets')->get();
        $requirements = Requirement::query()->where('standard_id', $bank->standard_id)
            ->where('is_mandatory', true)->get(['id', 'code', 'title']);
        $controls = Control::query()->where('standard_id', $bank->standard_id)
            ->where('is_group', false)->get(['id', 'code', 'title']);
        $mappedRequirements = $questions->flatMap(fn ($q) => $q->targets->pluck('requirement_id'))->filter()->unique();
        $mappedControls = $questions->flatMap(fn ($q) => $q->targets->pluck('control_id'))->filter()->unique();
        $codes = $questions->pluck('code')->all();
        $brokenBranches = [];
        $invalidQuestions = [];

        foreach ($questions as $question) {
            if ($question->targets->isEmpty()) { $invalidQuestions[] = $question->code; }
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
            'catalog_warning' => 'Cakupan hanya dapat dijamin terhadap master standar yang telah dimuat. Validasi katalog terhadap standar berlisensi diperlukan.',
        ];
    }

    public function publish(GuidedQuestionBank $bank, User $actor): array
    {
        if (! $actor->can('guided.bank.manage')) {
            throw ValidationException::withMessages(['authorization' => 'Tidak berwenang menerbitkan bank pertanyaan.']);
        }
        if ($bank->status !== 'draft' || $bank->questions()->count() === 0) {
            throw ValidationException::withMessages(['bank' => 'Hanya bank draft berisi pertanyaan yang bisa diterbitkan.']);
        }
        $coverage = $this->coverage($bank);
        if ($coverage['missing_requirements'] || $coverage['missing_controls']
            || $coverage['broken_branches'] || $coverage['invalid_questions']) {
            throw ValidationException::withMessages(['coverage' => 'Bank belum lengkap; periksa persyaratan, kontrol, percabangan, dan pilihan jawaban.']);
        }
        $bank->update(['status' => 'published']);
        return $coverage;
    }
}
