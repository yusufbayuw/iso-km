<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceScheduleResource\Pages;
use App\Models\EvidenceSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceScheduleResource extends Resource
{
    protected static ?string $model = EvidenceSchedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Assurance';
    protected static ?string $navigationLabel = 'Evidence Recurring';
    protected static ?string $modelLabel = 'Jadwal Evidence';
    protected static ?string $pluralModelLabel = 'Evidence Recurring';
    protected static ?int $navigationSort = 45;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Evidence yang Dibutuhkan')->required()->columnSpanFull(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Select::make('evidence_type')->label('Jenis Evidence')->options(['record' => 'Rekaman','screenshot' => 'Screenshot','report' => 'Laporan','log' => 'Log','configuration' => 'Konfigurasi','other' => 'Lainnya'])->default('record')->required(),
            Forms\Components\Select::make('frequency')->label('Frekuensi')->options(['weekly' => 'Mingguan','monthly' => 'Bulanan','quarterly' => 'Triwulanan','semiannual' => 'Semester','annual' => 'Tahunan','custom' => 'Custom'])->default('monthly')->live()->required(),
            Forms\Components\TextInput::make('interval_days')->label('Interval Custom (hari)')->numeric()->minValue(1)->visible(fn (Forms\Get $get): bool => $get('frequency') === 'custom'),
            Forms\Components\TextInput::make('lead_days')->label('Buat Request H-')->numeric()->minValue(0)->default(7)->required(),
            Forms\Components\TextInput::make('validity_days')->label('Masa Berlaku Evidence (hari)')->numeric()->minValue(1),
            Forms\Components\DatePicker::make('next_due_at')->label('Jatuh Tempo Berikutnya')->required()->native(false),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Petunjuk Pengumpulan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Evidence')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem'),
            Tables\Columns\TextColumn::make('frequency')->label('Frekuensi')->badge(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('next_due_at')->label('Next Due')->date('d/m/Y')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageEvidenceSchedules::route('/')]; }
}
