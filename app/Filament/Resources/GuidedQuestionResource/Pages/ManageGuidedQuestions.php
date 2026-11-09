<?php

namespace App\Filament\Resources\GuidedQuestionResource\Pages;

use App\Filament\Resources\GuidedQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGuidedQuestions extends ManageRecords
{
    protected static string $resource = GuidedQuestionResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
