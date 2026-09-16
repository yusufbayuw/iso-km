<?php

namespace App\Filament\Resources\CompetencyRecordResource\Pages;

use App\Filament\Resources\CompetencyRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCompetencyRecords extends ManageRecords
{
    protected static string $resource = CompetencyRecordResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
