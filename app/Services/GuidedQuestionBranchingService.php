<?php

namespace App\Services;

use App\Models\GuidedAnswer;
use App\Models\GuidedAssessment;
use App\Models\GuidedAssignment;
use App\Models\GuidedConsolidation;
use App\Models\GuidedQuestion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GuidedQuestionBranchingService
{
    /** Conditional follow-ups do not become independent required control assessments. */
    public function rootQuestions(GuidedAssessment $run): Collection
    {
        $questions = $run->bank->questions()->get();
        $destinations = $questions->flatMap(fn (GuidedQuestion $question) =>
            collect($question->branch_rules ?? [])->pluck('next_code'))->filter()->unique();
        return $questions->reject(fn (GuidedQuestion $question) => $destinations->contains($question->code))->values();
    }

    public function assignRoots(GuidedAssessment $run, User $respondent, User $coordinator): int
    {
        $questions = $this->rootQuestions($run);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['questions' => 'Tidak ada pertanyaan awal yang dapat diberikan. Periksa percabangan bank.']);
        }
        foreach ($questions as $question) {
            app(GuidedAssessmentWorkflowService::class)->assign($run, $question, $respondent, $coordinator);
        }
        return $questions->count();
    }

    /**
     * Recalculate the reachable branch graph from the latest answer PER context.
     * Source answers remain immutable when a previously active branch closes.
     * The calling workflow already holds the assessment lock/transaction.
     */
    public function assignNext(GuidedAssignment $assignment, GuidedAnswer $answer): int
    {
        $run = $assignment->assessment;
        $questions = $run->bank->questions()->get();
        $byId = $questions->keyBy('id');
        $byCode = $questions->keyBy('code');

        $assignments = $run->assignments()->where('user_id', $assignment->user_id)->get()->keyBy('guided_question_id');
        $latestAnswers = $run->answers()->where('respondent_id', $assignment->user_id)
            ->orderByDesc('id')->get()
            ->unique(fn (GuidedAnswer $entry) => $entry->guided_question_id.'|'.$entry->context_key)
            ->groupBy('guided_question_id');

        $desired = [];
        $queue = [];
        foreach ($assignments as $assigned) {
            if (! $assigned->is_auto_assigned) {
                $queue[] = $assigned->guided_question_id;
            }
        }

        while ($queue) {
            $questionId = array_shift($queue);
            if (isset($desired[$questionId])) { continue; }
            $desired[$questionId] = true;
            $question = $byId->get($questionId);
            $entry = $assignments->get($questionId);
            if (! $question || ! $entry) { continue; }

            // A revived branch needs a new answer: stale historical submissions
            // are never used to activate its children.
            if ($entry->status !== 'submitted') { continue; }

            foreach ($latestAnswers->get($questionId, collect()) as $response) {
                foreach ($question->branch_rules ?? [] as $rule) {
                    if (! is_array($rule) || (string) ($rule['when'] ?? '') !== (string) ($response->response['value'] ?? '')) {
                        continue;
                    }
                    $next = $byCode->get($rule['next_code'] ?? '');
                    if (! $next) {
                        throw ValidationException::withMessages(['branches' => 'Referensi pertanyaan lanjutan tidak ditemukan.']);
                    }
                    $queue[] = $next->id;
                }
            }
        }

        $created = 0;
        foreach ($desired as $questionId => $_) {
            if (isset($assignments[$questionId])) {
                $existing = $assignments[$questionId];
                if ($existing->is_auto_assigned && $existing->status === 'skipped') {
                    $existing->update(['status' => 'assigned']);
                }
                continue;
            }
            $run->assignments()->create([
                'guided_question_id' => $questionId,
                'user_id' => $assignment->user_id,
                'status' => 'assigned',
                'is_auto_assigned' => true,
            ]);
            $created++;
        }

        foreach ($assignments as $entry) {
            if (! $entry->is_auto_assigned || isset($desired[$entry->guided_question_id]) || $entry->status === 'skipped') {
                continue;
            }
            $entry->update(['status' => 'skipped']);
            GuidedConsolidation::query()
                ->where('guided_assessment_id', $run->id)
                ->where('guided_question_id', $entry->guided_question_id)
                ->where('is_current', true)->update(['is_current' => false]);
        }

        return $created;
    }
}
