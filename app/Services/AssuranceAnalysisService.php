<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\AssuranceSnapshot;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\StatementOfApplicability;

class AssuranceAnalysisService
{
    public function refreshAll(): int
    {
        $count = 0;
        ManagementSystem::query()->each(function (ManagementSystem $system) use (&$count): void {
            $this->refresh($system);
            $count++;
        });

        return $count;
    }

    public function refresh(ManagementSystem $system): AssuranceSnapshot
    {
        $assessments = RequirementAssessment::query()->where('management_system_id', $system->id);
        $assessmentTotal = (clone $assessments)->count();
        $assessmentPositive = (clone $assessments)->whereIn('status', ['conformant', 'implemented'])->count();
        $assessmentPartial = (clone $assessments)->whereIn('status', ['partial', 'partially_conformant', 'in_progress'])->count();
        $requirementReadiness = $this->percent($assessmentPositive + ($assessmentPartial * 0.5), $assessmentTotal);

        $soa = StatementOfApplicability::query()->where('management_system_id', $system->id)->where('applicable', true);
        $applicableControls = (clone $soa)->pluck('control_id');
        $applicableTotal = $applicableControls->count();
        $implemented = (clone $soa)->whereIn('implementation_status', ['implemented', 'effective'])->count();
        $controlImplementation = $this->percent($implemented, $applicableTotal);

        $verifiedEvidence = Evidence::query()
            ->where('management_system_id', $system->id)
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            });

        $coveredControlIds = (clone $verifiedEvidence)
            ->whereHas('controls', fn ($query) => $query->whereIn('controls.id', $applicableControls))
            ->with('controls:id')
            ->get()
            ->flatMap(fn (Evidence $evidence) => $evidence->controls->pluck('id'))
            ->intersect($applicableControls)
            ->unique()
            ->count();
        $evidenceCoverage = $this->percent($coveredControlIds, $applicableTotal);

        $allEvidence = Evidence::query()->where('management_system_id', $system->id)->where('status', '!=', 'archived');
        $evidenceTotal = (clone $allEvidence)->count();
        $freshEvidence = (clone $allEvidence)->where(function ($query): void {
            $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
        })->count();
        $evidenceFreshness = $this->percent($freshEvidence, $evidenceTotal);

        $allRisks = Risk::query()->where('management_system_id', $system->id);
        $riskTotal = (clone $allRisks)->count();
        $openRisksQuery = (clone $allRisks)->where('status', '!=', 'closed');
        $openRisks = (clone $openRisksQuery)->count();
        $highRisks = (clone $openRisksQuery)->where(function ($query): void {
            $query->where('residual_score', '>=', 12)
                ->orWhere(function ($query): void {
                    $query->whereNull('residual_score')->where('inherent_score', '>=', 12);
                });
        })->count();
        $riskHealth = match (true) {
            $riskTotal === 0 => 0.0,
            $openRisks === 0 => 100.0,
            default => max(0.0, 100.0 - (($highRisks / $openRisks) * 100.0)),
        };

        $completedAudits = Audit::query()->where('management_system_id', $system->id)->where('status', 'completed')->count();
        $findings = AuditFinding::query()->whereHas('audit', fn ($query) => $query->where('management_system_id', $system->id));
        $findingTotal = (clone $findings)->count();
        $openFindings = (clone $findings)->where('status', '!=', 'closed')->count();
        $findingsHealth = match (true) {
            $completedAudits === 0 => 0.0,
            $findingTotal === 0 => 100.0,
            default => max(0.0, 100.0 - (($openFindings / $findingTotal) * 100.0)),
        };

        $actions = ActionItem::query()->where('management_system_id', $system->id)->where('status', '!=', 'completed');
        $openActions = (clone $actions)->count();
        $overdueActions = (clone $actions)->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $actionHealth = $openActions === 0 ? 100.0 : max(0.0, 100.0 - (($overdueActions / $openActions) * 100.0));

        $confidencePoints = 0;
        $confidencePoints += $assessmentTotal > 0 && $requirementReadiness >= 80 ? 1 : 0;
        $confidencePoints += $applicableTotal > 0 && $controlImplementation >= 80 ? 2 : ($applicableTotal > 0 && $controlImplementation >= 50 ? 1 : 0);
        $confidencePoints += $applicableTotal > 0 && $evidenceCoverage >= 80 ? 2 : ($applicableTotal > 0 && $evidenceCoverage >= 50 ? 1 : 0);
        $confidencePoints += $evidenceTotal > 0 && $evidenceFreshness >= 90 ? 1 : 0;
        $confidencePoints += $riskTotal > 0 && $riskHealth >= 80 ? 1 : 0;
        $confidencePoints += $completedAudits > 0 && $findingsHealth >= 80 ? 1 : 0;
        $confidencePoints += $actionHealth >= 80 ? 1 : 0;
        $confidence = match (true) {
            $confidencePoints >= 8 => 'high',
            $confidencePoints >= 5 => 'medium',
            default => 'low',
        };

        return AssuranceSnapshot::query()->updateOrCreate(
            ['management_system_id' => $system->id],
            [
                'requirement_readiness' => round($requirementReadiness, 2),
                'control_implementation' => round($controlImplementation, 2),
                'evidence_coverage' => round($evidenceCoverage, 2),
                'evidence_freshness' => round($evidenceFreshness, 2),
                'risk_health' => round($riskHealth, 2),
                'findings_health' => round($findingsHealth, 2),
                'action_health' => round($actionHealth, 2),
                'confidence_level' => $confidence,
                'attention_counts' => [
                    'high_risks' => $highRisks,
                    'open_findings' => $openFindings,
                    'open_actions' => $openActions,
                    'overdue_actions' => $overdueActions,
                    'expired_evidence' => Evidence::query()->where('management_system_id', $system->id)->where('status', 'expired')->count(),
                    'unverified_evidence' => Evidence::query()->where('management_system_id', $system->id)->where('verification_status', '!=', 'verified')->where('status', '!=', 'archived')->count(),
                    'risks_registered' => $riskTotal,
                    'completed_audits' => $completedAudits,
                ],
                'calculated_at' => now(),
            ],
        );
    }

    private function percent(float|int $numerator, int $denominator): float
    {
        return $denominator > 0 ? min(100.0, ($numerator / $denominator) * 100.0) : 0.0;
    }
}
