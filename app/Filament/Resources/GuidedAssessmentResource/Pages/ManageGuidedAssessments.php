<?php

namespace App\Filament\Resources\GuidedAssessmentResource\Pages;

use App\Filament\Resources\GuidedAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGuidedAssessments extends ManageRecords
{
    protected static string $resource = GuidedAssessmentResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
