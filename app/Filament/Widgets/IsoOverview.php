<?php

namespace App\Filament\Widgets;

use App\Models\AuditFinding;
use App\Models\ComplianceObligation;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\Evidence;
use App\Models\ManagementReviewAction;
use App\Models\Objective;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\StatementOfApplicability;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IsoOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $assessed = RequirementAssessment::query()->where('status', '!=', 'not_assessed')->count();
        $conformant = RequirementAssessment::query()->where('status', 'conformant')->count();
        $readiness = $assessed > 0 ? round(($conformant / $assessed) * 100) : 0;
        $applicable = StatementOfApplicability::query()->where('applicable', true)->count();
        $implemented = StatementOfApplicability::query()->where('applicable', true)->where('implementation_status', 'implemented')->count();
        $coverage = $applicable > 0 ? round(($implemented / $applicable) * 100) : 0;
        $reviewDue = Document::query()->whereNotNull('next_review_at')->whereDate('next_review_at', '<=', now()->addDays(30))->count()
            + Evidence::query()->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(30))->count()
            + ComplianceObligation::query()->whereNotNull('next_review_at')->whereDate('next_review_at', '<=', now()->addDays(30))->count();
        $overdueActions = CorrectiveAction::query()->whereNotNull('due_date')->whereDate('due_date', '<', today())->where('status', '!=', 'completed')->count()
            + ManagementReviewAction::query()->whereNotNull('due_date')->whereDate('due_date', '<', today())->where('status', '!=', 'completed')->count();

        return [
            Stat::make('Readiness Requirement', $readiness.'%')->description($conformant.' sesuai dari '.$assessed.' yang sudah dinilai')->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Implementasi Control', $coverage.'%')->description($implemented.' dari '.$applicable.' control applicable')->icon('heroicon-o-shield-check'),
            Stat::make('Risiko Tinggi Terbuka', Risk::query()->where('inherent_score', '>=', 12)->where('status', '!=', 'closed')->count())->description('Skor inherent ≥ 12')->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Open Finding', AuditFinding::query()->where('status', '!=', 'closed')->count())->description('Finding audit yang belum ditutup')->icon('heroicon-o-document-magnifying-glass'),
            Stat::make('Objective At Risk', Objective::query()->where('status', 'at_risk')->count())->description('Objective/KPI memerlukan perhatian')->icon('heroicon-o-chart-bar-square'),
            Stat::make('Action Overdue', $overdueActions)->description('CAPA / management review action overdue')->icon('heroicon-o-clock'),
            Stat::make('Review Due', $reviewDue)->description('Dokumen, evidence, kewajiban ≤ 30 hari')->icon('heroicon-o-bell-alert'),
        ];
    }
}
