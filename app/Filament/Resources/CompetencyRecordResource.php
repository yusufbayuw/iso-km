<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompetencyRecordResource\Pages;
use App\Models\CompetencyRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompetencyRecordResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = CompetencyRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'People & Competence';
    protected static ?string $modelLabel = 'Catatan Kompetensi';
    protected static ?string $pluralModelLabel = 'Competence Register';
    protected static ?int $navigationSort = 180;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name', modifyQueryUsing: fn ($query, $record) => app(\App\Services\ModelScopeAuthorizationService::class)->scopeAvailableSystems($query, auth()->user(), $record?->management_system_id))->required()->searchable()->preload(),
            Forms\Components\Select::make('user_id')->label('Personel')->relationship('user', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('competency')->label('Kompetensi')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('source')->label('Sumber Kompetensi')->placeholder('Training, sertifikasi, pengalaman, assessment'),
            Forms\Components\TextInput::make('proficiency_level')->label('Level')->placeholder('Basic / Intermediate / Advanced'),
            Forms\Components\Select::make('assessed_by')->label('Assessor')->relationship('assessor', 'name')->searchable()->preload(),
            Forms\Components\DatePicker::make('assessed_at')->label('Tanggal Assessment')->native(false),
            Forms\Components\DatePicker::make('valid_until')->label('Valid Sampai')->native(false),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif','expiring' => 'Akan Kedaluwarsa','expired' => 'Kedaluwarsa','revoked' => 'Dicabut'])->required()->default('active'),
            Forms\Components\Select::make('evidence_id')->label('Evidence')->relationship('evidence', 'title')->searchable()->preload(),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'code')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'code')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('Personel')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('competency')->label('Kompetensi')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('proficiency_level')->label('Level')->placeholder('-'),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('valid_until')->label('Valid Sampai')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('evidence.title')->label('Evidence')->placeholder('-')->limit(30),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageCompetencyRecords::route('/')]; }
}
