<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssuranceSnapshotResource\Pages;
use App\Models\AssuranceSnapshot;
use App\Services\AssuranceReportService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssuranceSnapshotResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = AssuranceSnapshot::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'Assurance';
    protected static ?string $navigationLabel = 'Assurance Overview';
    protected static ?string $modelLabel = 'Assurance Snapshot';
    protected static ?string $pluralModelLabel = 'Assurance Overview';
    protected static ?int $navigationSort = 35;

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(), Tables\Columns\TextColumn::make('managementSystem.name')->label('Management System')->wrap(),
            Tables\Columns\TextColumn::make('requirement_readiness')->label('Readiness')->suffix('%')->sortable(), Tables\Columns\TextColumn::make('control_implementation')->label('Implementation')->suffix('%')->sortable(),
            Tables\Columns\TextColumn::make('evidence_coverage')->label('Evidence Coverage')->suffix('%')->sortable(), Tables\Columns\TextColumn::make('evidence_freshness')->label('Freshness')->suffix('%')->sortable(),
            Tables\Columns\TextColumn::make('risk_health')->label('Risk Health')->suffix('%')->sortable(), Tables\Columns\TextColumn::make('findings_health')->label('Finding Health')->suffix('%')->sortable(),
            Tables\Columns\TextColumn::make('action_health')->label('Action Health')->suffix('%')->sortable(), Tables\Columns\TextColumn::make('confidence_level')->label('Confidence')->badge(), Tables\Columns\TextColumn::make('calculated_at')->label('Updated')->since()->sortable(),
        ])->actions([
            Tables\Actions\Action::make('download_report')->label('CSV Report')->icon('heroicon-o-arrow-down-tray')->visible(fn (): bool => auth()->user()?->can('assurance.report') ?? false)->action(function (AssuranceSnapshot $record) {
                $csv = app(AssuranceReportService::class)->toCsv($record->managementSystem);
                $filename = 'assurance-'.strtolower($record->managementSystem->code).'-'.today()->format('Ymd').'.csv';
                return response()->streamDownload(fn () => print($csv), $filename, ['Content-Type' => 'text/csv']);
            }),
        ])->bulkActions([]);
    }

    public static function canCreate(): bool { return false; }
    public static function getPages(): array { return ['index' => Pages\ManageAssuranceSnapshots::route('/')]; }
}
