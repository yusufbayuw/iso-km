<?php

namespace App\Filament\Resources\ExternalAuditAccessResource\Pages;

use App\Filament\Resources\ExternalAuditAccessResource;
use App\Models\ExternalAuditAccess;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageExternalAuditAccesses extends ManageRecords
{
    protected static string $resource = ExternalAuditAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->after(function (ExternalAuditAccess $record): void {
                $record->user?->assignRole('external_auditor');
            }),
        ];
    }
}
