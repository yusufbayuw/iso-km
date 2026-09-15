<?php

namespace App\Filament\Widgets;

use App\Models\ActionItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyWorkOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool { return auth()->user()?->can('view_any_action::item') ?? false; }

    protected function getStats(): array
    {
        $query = ActionItem::query()->where('status', '!=', 'completed');
        $user = auth()->user();
        if (! $user?->can('action_item.view_all')) { $query->where('owner_id', $user?->id); }
        $urgent = (clone $query)->where(function ($query): void { $query->where('priority', 'critical')->orWhere(fn ($query) => $query->whereNotNull('due_at')->where('due_at', '<', now())); })->count();
        $thisWeek = (clone $query)->whereNotNull('due_at')->whereBetween('due_at', [now(), now()->addDays(7)])->count();
        $evidence = (clone $query)->where('category', 'evidence_collection')->count();
        $all = (clone $query)->count();
        return [
            Stat::make('Perlu Perhatian', $urgent)->description('Critical / overdue')->icon('heroicon-o-exclamation-triangle'),
            Stat::make('7 Hari ke Depan', $thisWeek)->description('Pekerjaan dengan due date dekat')->icon('heroicon-o-calendar-days'),
            Stat::make('Evidence Pending', $evidence)->description('Evidence yang harus dikumpulkan')->icon('heroicon-o-paper-clip'),
            Stat::make('Open Work', $all)->description('Seluruh pekerjaan aktif')->icon('heroicon-o-inbox-stack'),
        ];
    }
}
