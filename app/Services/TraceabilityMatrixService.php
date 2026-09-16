<?php

namespace App\Services;

use App\Models\AuditFinding;
use App\Models\Document;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use Illuminate\Database\Eloquent\Builder;

class TraceabilityMatrixService
{
    public function build(ManagementSystem $system): array
    {
        $assessments = RequirementAssessment::query()
            ->where('management_system_id', $system->id)
            ->with(['requirement.controls', 'owner'])
            ->get()
            ->sortBy(fn (RequirementAssessment $assessment) => $this->sortKey($assessment->requirement?->code));

        $rows = $assessments->map(fn (RequirementAssessment $assessment) => $this->row($system, $assessment))->values();

        return [
            'system' => $system->loadMissing(['standard', 'scope']),
            'summary' => [
                'requirements' => $rows->count(),
                'gaps' => $rows->whereIn('assessment_status', ['not_assessed', 'nonconformant'])->count(),
                'evidence_gaps' => $rows->where('verified_fresh_evidence', 0)->count(),
                'attention' => $rows->where('attention_state', 'attention')->count(),
                'supported' => $rows->where('attention_state', 'supported')->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function row(ManagementSystem $system, RequirementAssessment $assessment): array
    {
        $requirement = $assessment->requirement;
        $controlIds = $requirement?->controls?->pluck('id')->all() ?? [];

        $documents = Document::query()
            ->whereHas('managementSystems', fn (Builder $query) => $query->where('management_systems.id', $system->id));
        $this->whereLinked($documents, $requirement->id, $controlIds);
        $documents = $documents->with('currentRevision')->get();

        $evidences = Evidence::query()->where('management_system_id', $system->id)->where('status', '!=', 'archived');
        $this->whereLinked($evidences, $requirement->id, $controlIds);
        $evidences = $evidences->get();

        $risks = Risk::query()->where('management_system_id', $system->id)->where('status', '!=', 'closed');
        $this->whereLinked($risks, $requirement->id, $controlIds);
        $risks = $risks->get();

        $findings = AuditFinding::query()
            ->whereHas('audit', fn (Builder $query) => $query->where('management_system_id', $system->id))
            ->where(function (Builder $query) use ($requirement, $controlIds): void {
                $query->where('requirement_id', $requirement->id);
                if ($controlIds !== []) {
                    $query->orWhereIn('control_id', $controlIds);
                }
            })
            ->with('correctiveActions')
            ->get();

        $verifiedFresh = $evidences->filter(fn (Evidence $evidence) =>
            $evidence->verification_status === 'verified'
            && $evidence->status === 'active'
            && (! $evidence->expires_at || $evidence->expires_at->gte(today()))
        )->count();

        $highRisks = $risks->filter(function (Risk $risk): bool {
            $score = $risk->residual_score ?? $risk->inherent_score ?? 0;
            return (int) $score >= 12;
        })->count();

        $openFindings = $findings->where('status', '!=', 'closed')->count();
        $openCapa = $findings
            ->flatMap->correctiveActions
            ->whereNotIn('status', ['closed', 'verified', 'completed'])
            ->count();

        $attentionState = $this->attentionState(
            assessmentStatus: $assessment->status,
            verifiedFreshEvidence: $verifiedFresh,
            highRisks: $highRisks,
            openFindings: $openFindings,
            openCapa: $openCapa,
        );

        return [
            'requirement_id' => $requirement->id,
            'requirement_code' => $requirement->code,
            'requirement_title' => $requirement->title,
            'assessment_status' => $assessment->status,
            'owner' => $assessment->owner?->name,
            'controls' => $requirement->controls->pluck('code')->implode(', '),
            'documents' => $documents->count(),
            'effective_documents' => $documents->where('status', 'effective')->count(),
            'evidences' => $evidences->count(),
            'verified_fresh_evidence' => $verifiedFresh,
            'open_risks' => $risks->count(),
            'high_risks' => $highRisks,
            'open_findings' => $openFindings,
            'open_capa' => $openCapa,
            'next_review_at' => $assessment->next_review_at?->toDateString(),
            'notes' => $assessment->notes,
            'attention_state' => $attentionState,
            'attention_state_color' => match ($attentionState) {
                'supported' => 'success',
                'attention' => 'danger',
                'needs_evidence' => 'warning',
                'gap' => 'danger',
                default => 'gray',
            },
        ];
    }

    private function whereLinked(Builder $query, int $requirementId, array $controlIds): void
    {
        $query->where(function (Builder $query) use ($requirementId, $controlIds): void {
            $query->whereHas('requirements', fn (Builder $query) => $query->where('requirements.id', $requirementId));

            if ($controlIds !== []) {
                $query->orWhereHas('controls', fn (Builder $query) => $query->whereIn('controls.id', $controlIds));
            }
        });
    }

    private function attentionState(string $assessmentStatus, int $verifiedFreshEvidence, int $highRisks, int $openFindings, int $openCapa): string
    {
        if (in_array($assessmentStatus, ['not_assessed', 'nonconformant'], true)) {
            return 'gap';
        }

        if ($highRisks > 0 || $openFindings > 0 || $openCapa > 0) {
            return 'attention';
        }

        if ($verifiedFreshEvidence === 0) {
            return 'needs_evidence';
        }

        return $assessmentStatus === 'conformant' ? 'supported' : 'review';
    }

    private function sortKey(?string $code): string
    {
        return str_pad((string) $code, 30, '0', STR_PAD_LEFT);
    }
}
