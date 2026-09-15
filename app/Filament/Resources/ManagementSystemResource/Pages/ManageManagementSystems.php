<?php

namespace App\Filament\Resources\ManagementSystemResource\Pages;

use App\Filament\Resources\ManagementSystemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageManagementSystems extends ManageRecords
{
    protected static string $resource = ManagementSystemResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
