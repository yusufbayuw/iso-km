<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RiskResource\Pages;
use App\Models\Risk;
use Filament\Forms;
use Filament\Forms\Form;
use App\Models\ManagementSystem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RiskResource extends Resource
{
    protected static ?string $model = Risk::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Risiko';
    protected static ?string $modelLabel = 'Risiko';
    protected static ?string $pluralModelLabel = 'Risk Register';
    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        $scale = [1 => '1 - Sangat Rendah',2 => '2 - Rendah',3 => '3 - Sedang',4 => '4 - Tinggi',5 => '5 - Sangat Tinggi'];
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->live()->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode Risiko')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Risiko')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('category')->label('Kategori'),
            Forms\Components\Select::make('owner_id')->label('Risk Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Select::make('likelihood')->label('Likelihood')->options($scale)->required()->default(1),
            Forms\Components\Select::make('impact')->label('Impact')->options($scale)->required()->default(1),
            Forms\Components\Select::make('treatment_strategy')->label('Strategi')->options(['avoid' => 'Avoid','mitigate' => 'Mitigate','transfer' => 'Transfer','accept' => 'Accept'])->required()->default('mitigate'),
            Forms\Components\Select::make('residual_likelihood')->label('Residual Likelihood')->options($scale),
            Forms\Components\Select::make('residual_impact')->label('Residual Impact')->options($scale),
            Forms\Components\Select::make('status')->label('Status')->options(['open' => 'Open','treatment' => 'Treatment','monitoring' => 'Monitoring','accepted' => 'Accepted','closed' => 'Closed'])->required()->default('open'),
            Forms\Components\DatePicker::make('target_date')->label('Target')->native(false),
            Forms\Components\Textarea::make('description')->label('Deskripsi Risiko')->columnSpanFull(),
            Forms\Components\Textarea::make('treatment_plan')->label('Rencana Perlakuan')->columnSpanFull(),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->multiple()->searchable()->preload()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Risiko')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('inherent_score')->label('Inherent')->badge()->color(fn (int $state): string => match (true) {$state >= 20 => 'danger',$state >= 12 => 'warning',$state >= 6 => 'info',default => 'success'})->alignCenter(),
            Tables\Columns\TextColumn::make('residual_score')->label('Residual')->badge()->placeholder('-')->alignCenter(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('target_date')->label('Target')->date('d/m/Y')->placeholder('-'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageRisks::route('/')]; }
}
