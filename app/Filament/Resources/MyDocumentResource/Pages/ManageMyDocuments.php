<?php

namespace App\Filament\Resources\MyDocumentResource\Pages;

use App\Filament\Resources\MyDocumentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMyDocuments extends ManageRecords
{
    protected static string $resource = MyDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
