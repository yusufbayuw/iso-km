<?php

namespace App\Services;

use App\Models\GuidedAssessment;
use App\Models\GuidedAnswerDraft;
use App\Models\GuidedDecision;
use App\Models\GuidedQuestionTarget;

class GuidedWizardCompletenessService
{
    /** A progress display only; canonical lock() remains the final authority. */
    public function report(GuidedAssessment $run): array
    {
        $assignments = $run->assignments()->get(['status']);
        $pendingAssignments = $assignments->filter(fn ($assignment) =>
            ! in_array($assignment->status, ['submitted', 'skipped'], true))->count();

        $expected = GuidedQuestionTarget::query()->whereHas('question', fn ($query) =>
            $query->where('guided_question_bank_id', $run->guided_question_bank_id))->count();

        $decisions = GuidedDecision::query()->whereHas('consolidation', fn ($query) =>
            $query->where('guided_assessment_id', $run->id)->where('is_current', true))->get();
        $approved = $decisions->filter(fn ($d) => $d->status === 'approved' && $d->synced_at !== null)
            ->pluck('guided_question_target_id')->unique()->count();
        $revisions = $decisions->where('status', 'revision_required')->count();
        $reviewPending = $decisions->filter(fn ($d) =>
            ! ($d->status === 'approved' && $d->synced_at !== null))->count();
        $notSubmitted = $assignments->count() - $pendingAssignments;
        // One assignment can hold several independent system/asset contexts.
        // A newer nonempty draft for another context must not be overlooked.
        $latestAnswers = $run->answers()->orderByDesc('id')->get()
            ->unique(fn ($answer) => $answer->guided_assignment_id.'|'.$answer->context_key)
            ->keyBy(fn ($answer) => $answer->guided_assignment_id.'|'.$answer->context_key);
        $unsentContexts = GuidedAnswerDraft::query()->where('guided_assessment_id', $run->id)
            ->with('assignment')->get()->filter(function (GuidedAnswerDraft $draft) use ($latestAnswers): bool {
                if ($draft->assignment?->status === 'skipped'
                    || trim((string) ($draft->response['value'] ?? '')) === '') {
                    return false;
                }
                $current = $latestAnswers->get($draft->guided_assignment_id.'|'.$draft->context_key);
                return ! $current || $draft->updated_at->greaterThan($current->submitted_at);
            })->count();

        $reasons = [];
        if (! $assignments->count()) { $reasons[] = 'Belum ada pertanyaan yang ditugaskan.'; }
        if ($pendingAssignments) { $reasons[] = $pendingAssignments.' penugasan masih perlu diisi.'; }
        if ($unsentContexts) { $reasons[] = $unsentContexts.' draft konteks belum dikirim.'; }
        if ($revisions) { $reasons[] = $revisions.' keputusan perlu revisi.'; }
        if ($reviewPending) { $reasons[] = $reviewPending.' keputusan menunggu review atau publikasi.'; }
        if ($approved < $expected) {
            $reasons[] = ($expected - $approved).' kriteria belum memiliki keputusan final yang dipublikasikan.';
        }

        return [
            'assignments_total' => $assignments->count(),
            'assignments_completed' => $notSubmitted,
            'assignments_pending' => $pendingAssignments,
            'draft_contexts_pending' => $unsentContexts,
            'criteria_total' => $expected,
            'criteria_approved' => $approved,
            'revisions' => $revisions,
            'reasons' => $reasons,
            'ready_to_lock' => count($reasons) === 0,
        ];
    }
}
