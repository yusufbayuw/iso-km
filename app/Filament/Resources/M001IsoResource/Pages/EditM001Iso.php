<?php

namespace App\Filament\Resources\M001IsoResource\Pages;

use App\Filament\Resources\M001IsoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditM001Iso extends EditRecord
{
    protected static string $resource = M001IsoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
