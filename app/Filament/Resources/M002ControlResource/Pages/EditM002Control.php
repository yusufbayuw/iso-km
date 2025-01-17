<?php

namespace App\Filament\Resources\M002ControlResource\Pages;

use App\Filament\Resources\M002ControlResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditM002Control extends EditRecord
{
    protected static string $resource = M002ControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
