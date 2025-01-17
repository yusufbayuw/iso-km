<?php

namespace App\Filament\Resources\M001IsoResource\Pages;

use App\Filament\Resources\M001IsoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewM001Iso extends ViewRecord
{
    protected static string $resource = M001IsoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
