<?php

namespace App\Providers;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { }

    public function boot(): void
    {
        // All ISO-KM selects (forms, actions, and table filters) show a searchable,
        // bounded initial list. Relationship queries can still search beyond it.
        Select::configureUsing(static function (Select $select): void {
            $select->searchable()->preload()->native(false)->optionsLimit(50);
        });
        SelectFilter::configureUsing(static function (SelectFilter $filter): void {
            $filter->searchable()->preload()->native(false)->optionsLimit(50);
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
