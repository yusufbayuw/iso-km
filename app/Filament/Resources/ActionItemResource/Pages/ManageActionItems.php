<?php

namespace App\Filament\Resources\ActionItemResource\Pages;

use App\Filament\Resources\ActionItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageActionItems extends ManageRecords
{
    protected static string $resource = ActionItemResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()->label('Tambah Pekerjaan Manual')]; }
}
