<?php

namespace App\Filament\Resources\M001IsoResource\Pages;

use App\Filament\Resources\M001IsoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListM001Isos extends ListRecords
{
    protected static string $resource = M001IsoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
