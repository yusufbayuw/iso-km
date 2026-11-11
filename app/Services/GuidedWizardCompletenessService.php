<?php

namespace App\Services;

use App\Models\GuidedAssessment;
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

        $reasons = [];
        if (! $assignments->count()) { $reasons[] = 'Belum ada pertanyaan yang ditugaskan.'; }
        if ($pendingAssignments) { $reasons[] = $pendingAssignments.' penugasan masih perlu diisi.'; }
        if ($revisions) { $reasons[] = $revisions.' keputusan perlu revisi.'; }
        if ($reviewPending) { $reasons[] = $reviewPending.' keputusan menunggu review atau publikasi.'; }
        if ($approved < $expected) {
            $reasons[] = ($expected - $approved).' kriteria belum memiliki keputusan final yang dipublikasikan.';
        }

        return [
            'assignments_total' => $assignments->count(),
            'assignments_completed' => $notSubmitted,
            'assignments_pending' => $pendingAssignments,
            'criteria_total' => $expected,
            'criteria_approved' => $approved,
            'revisions' => $revisions,
            'reasons' => $reasons,
            'ready_to_lock' => count($reasons) === 0,
        ];
    }
}
