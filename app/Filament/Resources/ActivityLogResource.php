<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?string $modelLabel = 'Audit Trail';
    protected static ?string $pluralModelLabel = 'Audit Trail';
    protected static ?int $navigationSort = 990;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('activity_log.view') ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('actor.name')->label('Pengguna')->placeholder('Sistem')->searchable(),
                Tables\Columns\TextColumn::make('event')->label('Event')->badge()->sortable(),
                Tables\Columns\TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn (string $state): string => class_basename($state))->sortable(),
                Tables\Columns\TextColumn::make('subject_id')->label('ID'),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->placeholder('-')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Detail Audit Trail')
                    ->form([
                        \Filament\Forms\Components\KeyValue::make('old_values')->label('Nilai Sebelumnya')->disabled(),
                        \Filament\Forms\Components\KeyValue::make('new_values')->label('Nilai Sesudahnya')->disabled(),
                        \Filament\Forms\Components\Textarea::make('user_agent')->label('User Agent')->disabled(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageActivityLogs::route('/')];
    }
}
