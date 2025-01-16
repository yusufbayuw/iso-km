<?php

namespace App\Filament\Resources;

use App\Filament\Resources\M001IsoResource\Pages;
use App\Filament\Resources\M001IsoResource\RelationManagers;
use App\Models\M001Iso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class M001IsoResource extends Resource
{
    protected static ?string $model = M001Iso::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $modelLabel = 'ISO';

    protected static ?string $slug = 'iso';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('nama'),
                TextEntry::make('kode'),
                TextEntry::make('keterangan'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required(),
                Forms\Components\TextInput::make('kode')
                    ->required(),
                Forms\Components\FileUpload::make('file')
                    ,
                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('file')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageM001Isos::route('/'),
        ];
    }
}
