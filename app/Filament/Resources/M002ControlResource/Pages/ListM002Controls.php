<?php

namespace App\Filament\Resources\M002ControlResource\Pages;

use Filament\Actions;
use App\Models\M001Iso;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\M002ControlResource;

class ListM002Controls extends ListRecords
{
    protected static string $resource = M002ControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        //$tabs = ['all' => Tab::make('Semua')->badge($this->getModel()::count())];

        $tabs = [];

        $types = M001Iso::withCount('control')
            ->get();

        foreach ($types as $type) {
            $name = $type->kode;
            $slug = str($name)->slug()->toString();

            $tabs[$slug] = Tab::make($name)
                ->badge($this->getModel()::where('iso_id', $type->id)->count())
                ->badgeColor("primary")
                ->modifyQueryUsing(function ($query) use ($type) {
                    return $query->where('iso_id', $type->id);
                });
        }

        return $tabs;
    }
}
