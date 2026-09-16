<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MyDocumentResource\Pages;
use App\Models\DocumentDistribution;
use App\Services\ActionItemSyncService;
use App\Services\DocumentWorkflowService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyDocumentResource extends Resource
{
    protected static ?string $model = DocumentDistribution::class;
    protected static ?string $slug = 'my-documents';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Pekerjaan Saya';
    protected static ?string $navigationLabel = 'Dokumen Saya';
    protected static ?string $modelLabel = 'Controlled Document';
    protected static ?string $pluralModelLabel = 'Dokumen Saya';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('document.acknowledge') ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['document', 'revision']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('distributed_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('document.code')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('document.title')->label('Dokumen')->wrap()->searchable(),
            Tables\Columns\TextColumn::make('revision.revision')->label('Revisi')->badge(),
            Tables\Columns\TextColumn::make('distributed_at')->label('Didistribusikan')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('acknowledgement_due_at')->label('Batas Baca')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('acknowledged_at')->label('Dibaca')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->actions([
            Tables\Actions\Action::make('download')
                ->label('Buka')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (DocumentDistribution $record): bool => (bool) $record->revision?->file_path)
                ->url(fn (DocumentDistribution $record): string => route('controlled-documents.download', $record))
                ->openUrlInNewTab(),
            Tables\Actions\Action::make('acknowledge')
                ->label('Sudah Dibaca')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (DocumentDistribution $record): bool => $record->status === 'pending' && $record->revision?->status === 'effective')
                ->requiresConfirmation()
                ->modalDescription('Dengan acknowledgement ini Anda menyatakan telah membaca controlled copy yang berlaku.')
                ->action(function (DocumentDistribution $record): void {
                    app(DocumentWorkflowService::class)->acknowledge($record, auth()->user());
                    app(ActionItemSyncService::class)->sync();
                    Notification::make()->success()->title('Dokumen telah di-acknowledge')->send();
                }),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'pending' => 'Pending','acknowledged' => 'Acknowledged','superseded' => 'Superseded',
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageMyDocuments::route('/')];
    }
}
