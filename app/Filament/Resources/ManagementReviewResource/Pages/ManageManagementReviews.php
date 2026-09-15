<?php

namespace App\Filament\Resources\ManagementReviewResource\Pages;

use App\Filament\Resources\ManagementReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageManagementReviews extends ManageRecords
{
    protected static string $resource = ManagementReviewResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
