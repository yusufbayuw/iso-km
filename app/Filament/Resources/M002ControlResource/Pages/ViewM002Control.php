<?php

namespace App\Filament\Resources\M002ControlResource\Pages;

use App\Filament\Resources\M002ControlResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewM002Control extends ViewRecord
{
    protected static string $resource = M002ControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
