<?php

namespace App\Filament\Resources;

use App\Filament\Resources\M002ControlResource\Pages;
use App\Filament\Resources\M002ControlResource\RelationManagers;
use App\Models\M002Control;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class M002ControlResource extends Resource
{
    protected static ?string $model = M002Control::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $modelLabel = 'Control';

    protected static ?string $slug = 'control';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('iso_id')
                    ->relationship('iso', 'id'),
                Forms\Components\TextInput::make('nama'),
                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('iso.kode')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama')
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
            'index' => Pages\ManageM002Controls::route('/'),
        ];
    }
}
