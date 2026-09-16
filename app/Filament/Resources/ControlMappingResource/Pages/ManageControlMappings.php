<?php

namespace App\Filament\Resources\ControlMappingResource\Pages;

use App\Filament\Resources\ControlMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageControlMappings extends ManageRecords
{
    protected static string $resource = ControlMappingResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
