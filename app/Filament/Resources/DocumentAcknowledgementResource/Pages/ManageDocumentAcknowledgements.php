<?php

namespace App\Filament\Resources\DocumentAcknowledgementResource\Pages;

use App\Filament\Resources\DocumentAcknowledgementResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentAcknowledgements extends ManageRecords
{
    protected static string $resource = DocumentAcknowledgementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
