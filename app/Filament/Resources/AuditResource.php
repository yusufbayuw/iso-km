<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use App\Models\Audit;
use Filament\Forms;
use Filament\Forms\Form;
use App\Models\ManagementSystem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = Audit::class;
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';
    protected static ?string $navigationGroup = 'Audit & Improvement';
    protected static ?string $modelLabel = 'Audit';
    protected static ?string $pluralModelLabel = 'Program Audit';
    protected static ?int $navigationSort = 110;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name', modifyQueryUsing: fn ($query, $record) => app(\App\Services\ModelScopeAuthorizationService::class)->scopeAvailableSystems($query, auth()->user(), $record?->management_system_id))->live()->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode Audit')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Judul Audit')->required()->columnSpanFull(),
            Forms\Components\Select::make('type')->label('Jenis')->options(['internal' => 'Internal','external' => 'Eksternal','supplier' => 'Supplier'])->required()->default('internal'),
            Forms\Components\Select::make('status')->label('Status')->options(['planning' => 'Perencanaan','scheduled' => 'Terjadwal','in_progress' => 'Berjalan','completed' => 'Selesai','cancelled' => 'Dibatalkan'])->required()->default('planning'),
            Forms\Components\Select::make('lead_auditor_id')->label('Lead Auditor')->relationship('leadAuditor', 'name')->searchable()->preload(),
            Forms\Components\DateTimePicker::make('planned_start_at')->label('Rencana Mulai')->seconds(false),
            Forms\Components\DateTimePicker::make('planned_end_at')->label('Rencana Selesai')->seconds(false),
            Forms\Components\DateTimePicker::make('actual_start_at')->label('Aktual Mulai')->seconds(false),
            Forms\Components\DateTimePicker::make('actual_end_at')->label('Aktual Selesai')->seconds(false),
            Forms\Components\Select::make('requirements')->label('Requirement yang Diaudit')->relationship('requirements', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control yang Diaudit')->relationship('controls', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('scope')->label('Scope Audit')->columnSpanFull(),
            Forms\Components\Textarea::make('objectives')->label('Tujuan Audit')->columnSpanFull(),
            Forms\Components\Textarea::make('criteria')->label('Kriteria Audit')->columnSpanFull(),
            Forms\Components\Repeater::make('checklistItems')->label('Checklist Audit')->relationship()->schema([
                Forms\Components\Select::make('requirement_id')->label('Requirement')->relationship('requirement', 'title')->searchable()->preload(),
                Forms\Components\Select::make('control_id')->label('Control')->relationship('control', 'title')->searchable()->preload(),
                Forms\Components\Textarea::make('question')->label('Pertanyaan / Checkpoint')->required()->columnSpanFull(),
                Forms\Components\Select::make('result')->label('Hasil')->options(['not_reviewed' => 'Belum Direview','conforming' => 'Conforming','nonconformity' => 'Nonconformity','observation' => 'Observation','opportunity' => 'OFI'])->required()->default('not_reviewed'),
                Forms\Components\Select::make('evidence_id')->label('Evidence')->relationship('evidence', 'title')->searchable()->preload(),
                Forms\Components\Textarea::make('notes')->label('Catatan Auditor')->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            ])->columns(2)->columnSpanFull()->collapsible()->itemLabel(fn (array $state): ?string => $state['question'] ?? null),
            Forms\Components\Textarea::make('summary')->label('Ringkasan Audit')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Audit')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('leadAuditor.name')->label('Lead Auditor')->placeholder('-'),
            Tables\Columns\TextColumn::make('planned_start_at')->label('Jadwal')->dateTime('d/m/Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('findings_count')->label('Finding')->counts('findings')->alignCenter(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()->visible(fn (Audit $record): bool => ! $record->checklistItems()->exists() && ! $record->findings()->exists())]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageAudits::route('/')]; }
}
