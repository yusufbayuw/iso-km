<?php

namespace App\Filament\Resources\CorrectiveActionResource\Pages;

use App\Filament\Resources\CorrectiveActionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCorrectiveActions extends ManageRecords
{
    protected static string $resource = CorrectiveActionResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
