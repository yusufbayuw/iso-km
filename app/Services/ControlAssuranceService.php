<?php

namespace App\Services;

use App\Models\AuditFinding;
use App\Models\StatementOfApplicability;

class ControlAssuranceService
{
    public function build(StatementOfApplicability $entry): array
    {
        $entry->loadMissing(['managementSystem.standard', 'control.requirements', 'owner']);
        $control = $entry->control;
        $systemId = $entry->management_system_id;

        $documents = $control->documents()
            ->whereHas('managementSystems', fn ($query) => $query->where('management_systems.id', $systemId))
            ->with('currentRevision')
            ->orderBy('code')
            ->get();

        $evidences = $control->evidences()
            ->where('management_system_id', $systemId)
            ->where('status', '!=', 'archived')
            ->with('owner')
            ->orderByDesc('captured_at')
            ->get();

        $risks = $control->risks()
            ->where('management_system_id', $systemId)
            ->with('owner')
            ->orderByDesc('residual_score')
            ->orderByDesc('inherent_score')
            ->get();

        $findings = AuditFinding::query()
            ->where('control_id', $control->id)
            ->whereHas('audit', fn ($query) => $query->where('management_system_id', $systemId))
            ->with(['audit', 'owner', 'correctiveActions'])
            ->latest()
            ->get();

        $verifiedFresh = $evidences->filter(fn ($evidence) =>
            $evidence->verification_status === 'verified'
            && $evidence->status === 'active'
            && (! $evidence->expires_at || $evidence->expires_at->gte(today()))
        )->count();

        return [
            'entry' => $entry,
            'control' => $control,
            'requirements' => $control->requirements,
            'documents' => $documents,
            'evidences' => $evidences,
            'risks' => $risks,
            'findings' => $findings,
            'summary' => [
                'documents' => $documents->count(),
                'evidences' => $evidences->count(),
                'verified_fresh_evidence' => $verifiedFresh,
                'open_risks' => $risks->where('status', '!=', 'closed')->count(),
                'open_findings' => $findings->where('status', '!=', 'closed')->count(),
                'open_capa' => $findings->flatMap->correctiveActions->whereNotIn('status', ['closed', 'verified', 'completed'])->count(),
            ],
        ];
    }
}
