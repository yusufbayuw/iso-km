<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\Audit;
use App\Models\ComplianceObligation;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\DocumentDistribution;
use App\Models\EvidenceRequest;
use App\Models\ManagementReview;
use App\Models\ManagementReviewAction;
use App\Models\Objective;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\StatementOfApplicability;
use Illuminate\Database\Eloquent\Model;

class ActionItemSyncService
{
    private array $seen = [];

    public function sync(): int
    {
        $this->seen = [];
        $changes = 0;
        $attentionHorizon = today()->addDays(30);

        CorrectiveAction::query()->whereNotIn('status', ['closed', 'verified', 'completed'])->each(fn (CorrectiveAction $item) => $changes += $this->project($item, 'capa', $item->owner_id, $item->finding?->audit?->management_system_id, 'Tindak lanjut CAPA', $item->action, $item->due_date));
        ManagementReviewAction::query()->whereNotIn('status', ['completed', 'closed'])->each(fn (ManagementReviewAction $item) => $changes += $this->project($item, 'management_review_action', $item->owner_id, $item->managementReview?->management_system_id, 'Tindak lanjut management review', $item->action, $item->due_date));
        EvidenceRequest::query()->whereNotIn('status', ['verified', 'waived'])->each(fn (EvidenceRequest $item) => $changes += $this->project($item, 'evidence_collection', $item->owner_id, $item->management_system_id, 'Kumpulkan evidence'.($item->schedule ? ': '.$item->schedule->title : ''), $item->period_label ? 'Periode '.$item->period_label : null, $item->due_at));
        DocumentDistribution::query()->with(['document', 'revision'])->where('status', 'pending')->each(fn (DocumentDistribution $item) => $changes += $this->project($item, 'document_acknowledgement', $item->user_id, $item->document?->managementSystems()->value('management_systems.id'), 'Acknowledge dokumen '.($item->document?->code ?? ''), 'Revisi '.($item->revision?->revision ?? '-'), $item->acknowledgement_due_at));
        RequirementAssessment::query()->with('requirement')->whereIn('status', ['gap', 'nonconformant', 'partial', 'partially_conformant'])->each(fn (RequirementAssessment $item) => $changes += $this->project($item, 'requirement_gap', $item->owner_id, $item->management_system_id, 'Tutup gap requirement '.($item->requirement?->code ?? ''), $item->notes ?: $item->requirement?->title, $item->next_review_at));
        StatementOfApplicability::query()->with('control')->where('applicable', true)->whereNotIn('implementation_status', ['implemented', 'effective'])->each(fn (StatementOfApplicability $item) => $changes += $this->project($item, 'control_implementation', $item->owner_id, $item->management_system_id, 'Implementasikan control '.($item->control?->code ?? ''), $item->implementation_notes ?: $item->control?->title, $item->next_review_at));
        Risk::query()->where('status', '!=', 'closed')->where(function ($query): void {
            $query->where('residual_score', '>=', 12)->orWhere(function ($query): void {
                $query->whereNull('residual_score')->where('inherent_score', '>=', 12);
            });
        })->each(fn (Risk $item) => $changes += $this->project($item, 'risk_treatment', $item->owner_id, $item->management_system_id, 'Tangani high risk '.$item->code, $item->title, $item->target_date));
        Document::query()->where('status', '!=', 'obsolete')->whereNotNull('next_review_at')->whereDate('next_review_at', '<=', $attentionHorizon)->each(fn (Document $item) => $changes += $this->project($item, 'document_review', $item->owner_id, $item->managementSystems()->value('management_systems.id'), 'Review dokumen '.$item->code, $item->title, $item->next_review_at));
        Audit::query()->whereIn('status', ['planning', 'scheduled'])->whereNotNull('planned_start_at')->whereDate('planned_start_at', '<=', $attentionHorizon)->each(fn (Audit $item) => $changes += $this->project($item, 'audit_preparation', $item->lead_auditor_id, $item->management_system_id, 'Persiapan audit '.$item->code, $item->title, $item->planned_start_at));
        Objective::query()->whereIn('status', ['active', 'at_risk'])->where(function ($query) use ($attentionHorizon): void {
            $query->where('status', 'at_risk')->orWhereDate('end_date', '<=', $attentionHorizon);
        })->each(fn (Objective $item) => $changes += $this->project($item, 'objective_review', $item->owner_id, $item->management_system_id, 'Review objective '.$item->code, $item->title, $item->end_date));
        ComplianceObligation::query()->where('status', 'active')->whereNotNull('next_review_at')->whereDate('next_review_at', '<=', $attentionHorizon)->each(fn (ComplianceObligation $item) => $changes += $this->project($item, 'compliance_review', $item->owner_id, $item->managementSystems()->value('management_systems.id'), 'Review kewajiban kepatuhan '.$item->code, $item->title, $item->next_review_at));
        ManagementReview::query()->whereIn('status', ['planned', 'scheduled'])->whereNotNull('scheduled_at')->whereDate('scheduled_at', '<=', $attentionHorizon)->each(fn (ManagementReview $item) => $changes += $this->project($item, 'management_review', $item->chairperson_id, $item->management_system_id, 'Persiapan management review '.$item->code, $item->title, $item->scheduled_at));

        $stale = ActionItem::query()->where('is_generated', true)->where('status', '!=', 'completed')->when($this->seen !== [], fn ($query) => $query->whereNotIn('action_key', array_keys($this->seen)))->update(['status' => 'completed', 'completed_at' => now()]);

        return $changes + $stale;
    }

    private function project(Model $source, string $category, ?int $ownerId, ?int $managementSystemId, string $title, ?string $description, mixed $dueAt): int
    {
        $key = 'auto:'.$category.':'.$source->getMorphClass().':'.$source->getKey();
        $this->seen[$key] = true;
        $item = ActionItem::query()->updateOrCreate(
            ['action_key' => $key],
            ['management_system_id' => $managementSystemId, 'owner_id' => $ownerId, 'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'category' => $category, 'title' => $title, 'description' => $description, 'priority' => $this->priority($dueAt, $category), 'status' => 'open', 'due_at' => $dueAt, 'completed_at' => null, 'is_generated' => true],
        );

        return $item->wasRecentlyCreated || $item->wasChanged() ? 1 : 0;
    }

    private function priority(mixed $dueAt, string $category): string
    {
        if (in_array($category, ['objective_review', 'risk_treatment', 'requirement_gap'], true)) { return 'high'; }
        if (! $dueAt) { return 'medium'; }
        $days = today()->diffInDays($dueAt, false);
        return match (true) { $days < 0 => 'critical', $days <= 3 => 'high', $days <= 14 => 'medium', default => 'low' };
    }
}
