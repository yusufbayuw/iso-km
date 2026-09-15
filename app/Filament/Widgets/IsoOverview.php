<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\Evidence;
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
        $attention = Document::query()->whereNotNull('next_review_at')->whereDate('next_review_at', '<=', now()->addDays(30))->count()
            + Evidence::query()->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(30))->count();

        return [
            Stat::make('Readiness Requirement', $readiness.'%')->description($conformant.' sesuai dari '.$assessed.' yang sudah dinilai')->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Implementasi Control', $coverage.'%')->description($implemented.' dari '.$applicable.' control applicable')->icon('heroicon-o-shield-check'),
            Stat::make('Risiko Tinggi Terbuka', Risk::query()->where('inherent_score', '>=', 12)->where('status', '!=', 'closed')->count())->description('Skor inherent ≥ 12')->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Perlu Perhatian', $attention)->description('Review dokumen / evidence ≤ 30 hari')->icon('heroicon-o-bell-alert'),
        ];
    }
}
