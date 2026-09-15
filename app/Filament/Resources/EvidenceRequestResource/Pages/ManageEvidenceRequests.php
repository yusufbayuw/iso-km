<?php

namespace App\Filament\Resources\EvidenceRequestResource\Pages;

use App\Filament\Resources\EvidenceRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEvidenceRequests extends ManageRecords
{
    protected static string $resource = EvidenceRequestResource::class;
    protected function getHeaderActions(): array { return []; }
}
