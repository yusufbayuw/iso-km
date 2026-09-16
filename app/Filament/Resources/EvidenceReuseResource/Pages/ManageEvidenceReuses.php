<?php

namespace App\Filament\Resources\EvidenceReuseResource\Pages;

use App\Filament\Resources\EvidenceReuseResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEvidenceReuses extends ManageRecords
{
    protected static string $resource = EvidenceReuseResource::class;
    protected function getHeaderActions(): array { return []; }
}
