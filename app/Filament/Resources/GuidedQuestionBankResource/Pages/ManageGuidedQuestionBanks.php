<?php

namespace App\Filament\Resources\GuidedQuestionBankResource\Pages;

use App\Filament\Resources\GuidedQuestionBankResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGuidedQuestionBanks extends ManageRecords
{
    protected static string $resource = GuidedQuestionBankResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
