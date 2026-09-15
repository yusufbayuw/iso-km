<?php

namespace App\Filament\Resources\ComplianceObligationResource\Pages;

use App\Filament\Resources\ComplianceObligationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageComplianceObligations extends ManageRecords
{
    protected static string $resource = ComplianceObligationResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
