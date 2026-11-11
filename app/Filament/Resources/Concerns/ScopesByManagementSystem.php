<?php

namespace App\Filament\Resources\Concerns;

use App\Services\ModelScopeAuthorizationService;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByManagementSystem
{
    public static function getEloquentQuery(): Builder
    {
        return app(ModelScopeAuthorizationService::class)
            ->scopeQuery(parent::getEloquentQuery(), auth()->user());
    }
}
