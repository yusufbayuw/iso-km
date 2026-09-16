<?php

namespace App\Filament\Resources\TrainingAssignmentResource\Pages;

use App\Filament\Resources\TrainingAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTrainingAssignments extends ManageRecords
{
    protected static string $resource = TrainingAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
