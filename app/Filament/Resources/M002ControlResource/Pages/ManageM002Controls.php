<?php

namespace App\Filament\Resources\M002ControlResource\Pages;

use App\Filament\Resources\M002ControlResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageM002Controls extends ManageRecords
{
    protected static string $resource = M002ControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
