<?php

namespace App\Filament\Resources\StandardResource\Pages;

use App\Filament\Resources\StandardResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStandards extends ManageRecords
{
    protected static string $resource = StandardResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
