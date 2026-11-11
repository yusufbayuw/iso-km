<?php

namespace App\Http\Controllers;

use App\Models\GuidedAssessment;
use App\Models\GuidedDecision;
use Illuminate\Http\Request;

class GuidedWizardReportController extends Controller
{
    public function __invoke(Request $request, GuidedAssessment $assessment)
    {
        abort_unless(config('iso-km.guided_assessment_enabled', false)
            && config('iso-km.guided_wizard_enabled', false), 404);
        $actor = $request->user();
        abort_unless($actor && $actor->can('guided.report.export')
            && $assessment->isMember($actor, ['coordinator']), 403);

        $decisions = GuidedDecision::query()->whereHas('consolidation', fn ($query) =>
            $query->where('guided_assessment_id', $assessment->id)->where('is_current', true))
            ->with(['consolidation.question', 'target.requirement', 'target.control'])
            ->orderBy('id')->get();
        $approved = $decisions->filter(fn ($decision) =>
            $decision->status === 'approved' && $decision->synced_at !== null);
        $progress = app(\App\Services\GuidedWizardCompletenessService::class)->report($assessment);

        return response()->view('filament.guided.printable-report', [
            'run' => $assessment->loadMissing(['system', 'creator']),
            'progress' => $progress,
            'approved' => $approved,
            'isFinal' => $assessment->locked_at !== null,
            'generatedAt' => now(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
