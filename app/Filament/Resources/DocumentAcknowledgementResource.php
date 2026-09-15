<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentAcknowledgementResource\Pages;
use App\Models\DocumentDistribution;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentAcknowledgementResource extends Resource
{
    protected static ?string $model = DocumentDistribution::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Dokumen & Bukti';
    protected static ?string $modelLabel = 'Acknowledgement Dokumen';
    protected static ?string $pluralModelLabel = 'Dokumen Saya';
    protected static ?int $navigationSort = 85;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('document.acknowledge') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['document', 'revision']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('acknowledgement_due_at')
            ->columns([
                Tables\Columns\TextColumn::make('document.code')->label('Kode')->searchable(),
                Tables\Columns\TextColumn::make('document.title')->label('Dokumen')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('revision.revision')->label('Revisi')->placeholder('-'),
                Tables\Columns\TextColumn::make('acknowledgement_due_at')->label('Batas Baca')->date('d/m/Y')->placeholder('-'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('acknowledged_at')->label('Dibaca')->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->label('Saya Sudah Membaca')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DocumentDistribution $record): bool => $record->status !== 'acknowledged' && $record->user_id === auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription('Dengan mengonfirmasi, Anda menyatakan telah membaca dan memahami dokumen/revisi yang didistribusikan.')
                    ->action(function (DocumentDistribution $record): void {
                        $record->update([
                            'status' => 'acknowledged',
                            'acknowledged_at' => now(),
                        ]);

                        Notification::make()->success()->title('Acknowledgement tercatat')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDocumentAcknowledgements::route('/')];
    }
}
