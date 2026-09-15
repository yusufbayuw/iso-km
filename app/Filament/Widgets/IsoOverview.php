<?php

namespace App\Filament\Widgets;

use App\Models\ActionItem;
use App\Models\AssuranceSnapshot;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IsoOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    public static function canView(): bool { return auth()->user()?->can('view_any_assurance::snapshot') ?? false; }
    protected function getStats(): array
    {
        $snapshots = AssuranceSnapshot::query();
        $count = (clone $snapshots)->count();
        $avg = fn (string $column): int => $count > 0 ? (int) round((float) (clone $snapshots)->avg($column)) : 0;
        $lowConfidence = (clone $snapshots)->where('confidence_level', 'low')->count();
        $criticalWork = ActionItem::query()->where('status', '!=', 'completed')->where('priority', 'critical')->count();
        return [
            Stat::make('Requirement Readiness', $avg('requirement_readiness').'%')->description('Assessment resmi, bukan klaim sertifikasi')->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Control Implementation', $avg('control_implementation').'%')->description('Control applicable yang sudah implemented')->icon('heroicon-o-shield-check'),
            Stat::make('Verified Evidence Coverage', $avg('evidence_coverage').'%')->description('Control applicable dengan evidence verified & fresh')->icon('heroicon-o-paper-clip'),
            Stat::make('Evidence Freshness', $avg('evidence_freshness').'%')->description('Evidence belum expired / archived')->icon('heroicon-o-clock'),
            Stat::make('Low Confidence', $lowConfidence)->description('Management system dengan assurance confidence rendah')->icon('heroicon-o-exclamation-circle'),
            Stat::make('Critical Work', $criticalWork)->description('Action item critical yang belum selesai')->icon('heroicon-o-bell-alert'),
        ];
    }
}
