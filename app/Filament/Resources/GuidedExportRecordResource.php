<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedExportRecordResource\Pages;
use App\Models\GuidedExportRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuidedExportRecordResource extends Resource
{
    protected static ?string $model = GuidedExportRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Riwayat Ekspor';
    protected static ?int $navigationSort = 26;

    public static function canViewAny(): bool { return config('iso-km.guided_assessment_enabled', false) && (auth()->user()?->can('guided.report.export') ?? false); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('assessment.memberships',
            fn ($q) => $q->where('user_id', auth()->id())->where('role', 'coordinator'))
            ->with(['assessment', 'template']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('assessment.name')->label('Asesmen')->searchable(),
            Tables\Columns\TextColumn::make('template.name')->label('Template'),
            Tables\Columns\IconColumn::make('is_final')->label('Final')->boolean(),
            Tables\Columns\TextColumn::make('output_sha256')->label('SHA256')->limit(16)->copyable(),
            Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i'),
        ])->actions([
            Tables\Actions\Action::make('download')->label('Unduh Arsip')->icon('heroicon-o-arrow-down-tray')
                ->action(function (GuidedExportRecord $record) {
                    $path = Storage::disk('local')->path($record->file_path);
                    if (! is_file($path) || ! hash_equals($record->output_sha256, hash_file('sha256', $path))) {
                        throw ValidationException::withMessages(['export' => 'File ekspor tidak tersedia atau tidak sesuai checksum arsip.']);
                    }
                    return response()->download($path, 'Assessment-'.$record->guided_assessment_id.'-'.$record->id.'.xlsx');
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ListGuidedExportRecords::route('/')]; }
}
