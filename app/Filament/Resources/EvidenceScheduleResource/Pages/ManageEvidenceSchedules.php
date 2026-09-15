<?php

namespace App\Filament\Resources\EvidenceScheduleResource\Pages;

use App\Filament\Resources\EvidenceScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEvidenceSchedules extends ManageRecords
{
    protected static string $resource = EvidenceScheduleResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
