<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatementOfApplicabilityResource\Pages;
use App\Models\StatementOfApplicability;
use App\Services\ControlAssuranceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StatementOfApplicabilityResource extends Resource
{
    protected static ?string $model = StatementOfApplicability::class;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Implementasi & Kepatuhan';
    protected static ?string $modelLabel = 'SoA Entry';
    protected static ?string $pluralModelLabel = 'Statement of Applicability';
    protected static ?int $navigationSort = 70;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('control_id')->label('Control')->relationship('control', 'title', modifyQueryUsing: fn (Builder $query) => $query->where('is_group', false))->required()->searchable()->preload(),
            Forms\Components\Toggle::make('applicable')->label('Applicable')->default(true),
            Forms\Components\Select::make('implementation_status')->label('Status Implementasi')->options(['not_started' => 'Belum Dimulai','planned' => 'Direncanakan','in_progress' => 'Dalam Proses','implemented' => 'Terimplementasi','not_applicable' => 'Tidak Berlaku'])->required()->default('not_started'),
            Forms\Components\Select::make('owner_id')->label('Control Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\DateTimePicker::make('last_reviewed_at')->label('Terakhir Direview')->seconds(false),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Textarea::make('justification')->label('Justifikasi Applicability')->columnSpanFull(),
            Forms\Components\Textarea::make('implementation_notes')->label('Catatan Implementasi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('control.code')->label('Control')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('control.title')->label('Judul')->wrap()->searchable(),
            Tables\Columns\IconColumn::make('applicable')->label('Applicable')->boolean(),
            Tables\Columns\TextColumn::make('implementation_status')->label('Implementasi')->badge()->color(fn (string $state): string => match ($state) {'implemented' => 'success','in_progress' => 'warning','not_applicable' => 'gray','planned' => 'info',default => 'danger'}),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('assurance360')
                ->label('Assurance 360')
                ->icon('heroicon-o-magnifying-glass-circle')
                ->modalHeading(fn (StatementOfApplicability $record): string => 'Assurance 360 — '.$record->control->code)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn (StatementOfApplicability $record) => view('filament.assurance.control-360', [
                    'data' => app(ControlAssuranceService::class)->build($record),
                ])),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageStatementsOfApplicability::route('/')]; }
}
