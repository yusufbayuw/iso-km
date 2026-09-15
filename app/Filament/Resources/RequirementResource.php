<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequirementResource\Pages;
use App\Models\Requirement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RequirementResource extends Resource
{
    protected static ?string $model = Requirement::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup = 'Referensi Standar';
    protected static ?string $modelLabel = 'Requirement';
    protected static ?string $pluralModelLabel = 'Requirements';
    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('standard_id')->label('Standar')->relationship('standard', 'code')->required()->searchable()->preload(),
            Forms\Components\Select::make('parent_id')->label('Induk')->relationship('parent', 'title')->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode')->required(),
            Forms\Components\TextInput::make('title')->label('Judul')->required(),
            Forms\Components\Select::make('type')->label('Jenis')->options(['clause' => 'Klausul','subclause' => 'Subklausul','requirement' => 'Requirement'])->required()->default('requirement'),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Forms\Components\Toggle::make('is_mandatory')->label('Wajib')->default(true),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('standard.code')->label('Standar')->sortable(),
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Requirement')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(),
            Tables\Columns\IconColumn::make('is_mandatory')->label('Wajib')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageRequirements::route('/')]; }
}
