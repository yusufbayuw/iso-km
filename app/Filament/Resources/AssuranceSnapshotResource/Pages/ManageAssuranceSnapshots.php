<?php

namespace App\Filament\Resources\AssuranceSnapshotResource\Pages;

use App\Filament\Resources\AssuranceSnapshotResource;
use App\Services\ActionItemSyncService;
use App\Services\AssuranceAnalysisService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageAssuranceSnapshots extends ManageRecords
{
    protected static string $resource = AssuranceSnapshotResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\Action::make('refresh')->label('Refresh Analysis')->icon('heroicon-o-arrow-path')->visible(fn (): bool => auth()->user()?->can('assurance.refresh') ?? false)->action(function (): void {
            app(ActionItemSyncService::class)->sync();
            $count = app(AssuranceAnalysisService::class)->refreshAll();
            Notification::make()->title("{$count} management system dianalisis ulang")->success()->send();
        })];
    }
}
