<?php

namespace App\Filament\Resources\M001IsoResource\Pages;

use App\Filament\Resources\M001IsoResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageM001Isos extends ManageRecords
{
    protected static string $resource = M001IsoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
