<?php

namespace App\Filament\Resources\GuidedExportTemplateResource\Pages;

use App\Filament\Resources\GuidedExportTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGuidedExportTemplates extends ManageRecords
{
    protected static string $resource = GuidedExportTemplateResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
