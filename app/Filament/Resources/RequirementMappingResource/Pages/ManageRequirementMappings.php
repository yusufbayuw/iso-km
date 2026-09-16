<?php

namespace App\Filament\Resources\RequirementMappingResource\Pages;

use App\Filament\Resources\RequirementMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRequirementMappings extends ManageRecords
{
    protected static string $resource = RequirementMappingResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
