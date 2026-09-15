<?php

namespace App\Filament\Resources\ComplianceScopeResource\Pages;

use App\Filament\Resources\ComplianceScopeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageComplianceScopes extends ManageRecords
{
    protected static string $resource = ComplianceScopeResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
