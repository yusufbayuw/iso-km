<?php

namespace App\Filament\Resources\RequirementAssessmentResource\Pages;

use App\Filament\Resources\RequirementAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRequirementAssessments extends ManageRecords
{
    protected static string $resource = RequirementAssessmentResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
