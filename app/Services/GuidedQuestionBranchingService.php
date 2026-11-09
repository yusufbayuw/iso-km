<?php

namespace App\Services;

use App\Models\GuidedAnswer;
use App\Models\GuidedAssessment;
use App\Models\GuidedAssignment;
use App\Models\GuidedQuestion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GuidedQuestionBranchingService
{
    /** Branch destinations are not asked until a preceding answer activates them. */
    public function rootQuestions(GuidedAssessment $run): Collection
    {
        $questions = $run->bank->questions()->get();
        $destinations = $questions->flatMap(fn ($question) =>
            collect($question->branch_rules ?? [])->pluck('next_code'))->filter()->unique();
        return $questions->reject(fn ($question) => $destinations->contains($question->code))->values();
    }

    public function assignRoots(GuidedAssessment $run, User $respondent, User $coordinator): int
    {
        $count = 0;
        $questions = $this->rootQuestions($run);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['questions' => 'Tidak ada pertanyaan awal yang dapat diberikan. Periksa percabangan bank.']);
        }
        foreach ($questions as $question) {
            app(GuidedAssessmentWorkflowService::class)->assign($run, $question, $respondent, $coordinator);
            $count++;
        }
        return $count;
    }

    public function assignNext(GuidedAssignment $assignment, GuidedAnswer $answer): int
    {
        $question = $assignment->question;
        $value = $answer->response['value'] ?? null;
        $codes = collect($question->branch_rules ?? [])
            ->filter(fn ($rule) => is_array($rule) && (string) ($rule['when'] ?? '') === (string) $value)
            ->pluck('next_code')->unique()->values()->all();
        if (! $codes) { return 0; }
        $followups = GuidedQuestion::query()->where('guided_question_bank_id', $question->guided_question_bank_id)
            ->whereIn('code', $codes)->get();
        if ($followups->count() !== count($codes)) {
            throw ValidationException::withMessages(['branches' => 'Aturan percabangan mengarah ke pertanyaan yang tidak tersedia.']);
        }
        $count = 0;
        foreach ($followups as $next) {
            $assignment->assessment->assignments()->firstOrCreate([
                'guided_question_id' => $next->id, 'user_id' => $assignment->user_id,
            ], ['status' => 'assigned']);
            $count++;
        }
        return $count;
    }
}
