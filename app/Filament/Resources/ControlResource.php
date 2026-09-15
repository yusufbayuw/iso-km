<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ControlResource\Pages;
use App\Models\Control;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ControlResource extends Resource
{
    protected static ?string $model = Control::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationGroup = 'Referensi Standar';
    protected static ?string $modelLabel = 'Control';
    protected static ?string $pluralModelLabel = 'Controls';
    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('standard_id')->label('Standar')->relationship('standard', 'code')->required()->searchable()->preload(),
            Forms\Components\Select::make('parent_id')->label('Induk/Grup')->relationship('parent', 'title')->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode')->required(),
            Forms\Components\TextInput::make('title')->label('Judul')->required(),
            Forms\Components\TextInput::make('category')->label('Kategori'),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Forms\Components\Toggle::make('is_group')->label('Hanya Grup/Kategori')->helperText('Grup tidak dihitung sebagai control yang harus dinilai.'),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('standard.code')->label('Standar')->sortable(),
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Control')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('category')->label('Kategori')->toggleable(),
            Tables\Columns\IconColumn::make('is_group')->label('Grup')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageControls::route('/')]; }
}
