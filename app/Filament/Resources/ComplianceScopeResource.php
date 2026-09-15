<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplianceScopeResource\Pages;
use App\Models\ComplianceScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ComplianceScopeResource extends Resource
{
    protected static ?string $model = ComplianceScope::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Fondasi ISO';
    protected static ?string $modelLabel = 'Lingkup';
    protected static ?string $pluralModelLabel = 'Lingkup Organisasi';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('Nama Lingkup')->required(),
            Forms\Components\Select::make('owner_id')->label('Penanggung Jawab')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Textarea::make('description')->label('Deskripsi Lingkup')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Lingkup')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('owner.name')->label('Penanggung Jawab')->placeholder('-'),
            Tables\Columns\TextColumn::make('management_systems_count')->label('Sistem')->counts('managementSystems')->alignRight(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageComplianceScopes::route('/')]; }
}
