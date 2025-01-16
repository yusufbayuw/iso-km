<?php

namespace App\Filament\Resources\M003DocumentResource\Pages;

use App\Filament\Resources\M003DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageM003Documents extends ManageRecords
{
    protected static string $resource = M003DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
