<?php

namespace App\Filament\Resources\MyTrainingResource\Pages;

use App\Filament\Resources\MyTrainingResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMyTraining extends ManageRecords
{
    protected static string $resource = MyTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
