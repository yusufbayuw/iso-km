<?php

namespace App\Filament\Resources\RiskResource\Pages;

use App\Filament\Resources\RiskResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRisks extends ManageRecords
{
    protected static string $resource = RiskResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
