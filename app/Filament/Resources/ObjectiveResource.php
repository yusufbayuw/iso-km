<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObjectiveResource\Pages;
use App\Models\Objective;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ObjectiveResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = Objective::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'Performance & Review';
    protected static ?string $modelLabel = 'Objective';
    protected static ?string $pluralModelLabel = 'Objectives & KPI';
    protected static ?int $navigationSort = 140;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode Objective')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Objective')->required()->columnSpanFull(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('metric')->label('Metric / KPI'),
            Forms\Components\TextInput::make('unit')->label('Satuan'),
            Forms\Components\TextInput::make('baseline_value')->label('Baseline')->numeric(),
            Forms\Components\TextInput::make('target_value')->label('Target')->numeric(),
            Forms\Components\Select::make('direction')->label('Arah Target')->options(['increase' => 'Semakin tinggi semakin baik','decrease' => 'Semakin rendah semakin baik','maintain' => 'Pertahankan rentang/level'])->required()->default('increase'),
            Forms\Components\Select::make('frequency')->label('Frekuensi Ukur')->options(['monthly' => 'Bulanan','quarterly' => 'Triwulanan','semester' => 'Semester','annual' => 'Tahunan'])->required()->default('monthly'),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif','at_risk' => 'At Risk','achieved' => 'Achieved','closed' => 'Closed'])->required()->default('active'),
            Forms\Components\DatePicker::make('start_date')->label('Mulai')->native(false),
            Forms\Components\DatePicker::make('end_date')->label('Berakhir')->native(false),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
            Forms\Components\Repeater::make('measurements')->label('Pengukuran KPI')->relationship()->schema([
                Forms\Components\DatePicker::make('period_date')->label('Periode')->required()->native(false),
                Forms\Components\TextInput::make('actual_value')->label('Aktual')->numeric(),
                Forms\Components\Select::make('status')->label('Status')->options(['on_track' => 'On Track','at_risk' => 'At Risk','off_track' => 'Off Track','achieved' => 'Achieved'])->required()->default('on_track'),
                Forms\Components\Select::make('measured_by')->label('Diukur Oleh')->relationship('measuredBy', 'name')->searchable()->preload(),
                Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            ])->columns(2)->columnSpanFull()->collapsible()->itemLabel(fn (array $state): ?string => $state['period_date'] ?? null),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Objective')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('metric')->label('KPI')->placeholder('-')->wrap(),
            Tables\Columns\TextColumn::make('target_value')->label('Target')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageObjectives::route('/')]; }
}
