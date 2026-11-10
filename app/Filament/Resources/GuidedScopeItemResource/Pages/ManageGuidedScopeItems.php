<?php

namespace App\Filament\Resources\GuidedScopeItemResource\Pages;

use App\Filament\Resources\GuidedScopeItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGuidedScopeItems extends ManageRecords
{
    protected static string $resource = GuidedScopeItemResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
