<?php

namespace App\Filament\Resources\AuditResource\Pages;

use App\Filament\Resources\AuditResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAudits extends ManageRecords
{
    protected static string $resource = AuditResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
