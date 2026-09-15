<?php

namespace App\Filament\Resources\StatementOfApplicabilityResource\Pages;

use App\Filament\Resources\StatementOfApplicabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStatementsOfApplicability extends ManageRecords
{
    protected static string $resource = StatementOfApplicabilityResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
