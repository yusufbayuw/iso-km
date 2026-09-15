<?php

namespace App\Filament\Resources\ObjectiveResource\Pages;

use App\Filament\Resources\ObjectiveResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageObjectives extends ManageRecords
{
    protected static string $resource = ObjectiveResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
