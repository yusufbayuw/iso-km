<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StandardResource\Pages;
use App\Models\Standard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StandardResource extends Resource
{
    protected static ?string $model = Standard::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Fondasi ISO';
    protected static ?string $modelLabel = 'Standar';
    protected static ?string $pluralModelLabel = 'Standar';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->maxLength(100),
            Forms\Components\TextInput::make('name')->label('Nama Standar')->required()->maxLength(255),
            Forms\Components\TextInput::make('edition')->label('Edisi/Tahun')->maxLength(50),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('edition')->label('Edisi')->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('requirements_count')->label('Requirement')->counts('requirements')->alignRight(),
            Tables\Columns\TextColumn::make('controls_count')->label('Control')->counts('controls')->alignRight(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageStandards::route('/')]; }
}
