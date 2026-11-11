<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\AuditFinding;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Objective;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\StatementOfApplicability;

class ManagementReviewContextService
{
    public function summary(int $managementSystemId): string
    {
        $system = ManagementSystem::query()->findOrFail($managementSystemId);
        $findingQuery = AuditFinding::query()->whereHas('audit', fn ($q) => $q
            ->where('management_system_id', $managementSystemId));

        $counts = [
            'Requirement belum sesuai / parsial' => RequirementAssessment::query()
                ->where('management_system_id', $managementSystemId)
                ->whereIn('status', ['not_assessed', 'nonconformant', 'partial', 'partially_conformant'])->count(),
            'Control berlaku belum diimplementasikan' => StatementOfApplicability::query()
                ->where('management_system_id', $managementSystemId)
                ->where('applicable', true)
                ->whereNotIn('implementation_status', ['implemented', 'effective'])->count(),
            'Evidence kedaluwarsa' => Evidence::query()
                ->where('management_system_id', $managementSystemId)
                ->where('status', '!=', 'archived')
                ->where(fn ($q) => $q->where('status', 'expired')->orWhereDate('expires_at', '<', today()))->count(),
            'Risiko terbuka tinggi' => Risk::query()
                ->where('management_system_id', $managementSystemId)
                ->where('status', '!=', 'closed')
                ->where(fn ($q) => $q->where('residual_score', '>=', 12)
                    ->orWhere(fn ($q) => $q->whereNull('residual_score')->where('inherent_score', '>=', 12)))->count(),
            'Temuan audit belum ditutup' => (clone $findingQuery)->where('status', '!=', 'closed')->count(),
            'Objective berisiko' => Objective::query()
                ->where('management_system_id', $managementSystemId)
                ->where('status', 'at_risk')->count(),
            'Tugas melewati tenggat' => ActionItem::query()
                ->where('management_system_id', $managementSystemId)
                ->where('status', '!=', 'completed')
                ->where('due_at', '<', now())->count(),
        ];

        $lines = [
            'Ringkasan sistem otomatis ('.now()->format('d/m/Y H:i').')',
            'Sistem: '.$system->code.' - '.$system->name,
            'Angka berikut merupakan kondisi data pada waktu ringkasan dibuat, bukan keputusan sertifikasi.',
        ];
        foreach ($counts as $label => $count) {
            $lines[] = $label.': '.$count;
        }

        return implode("\n", $lines);
    }
}
