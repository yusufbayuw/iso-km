<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\TrainingProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTrainingPrograms extends ManageRecords
{
    protected static string $resource = TrainingProgramResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
