<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManagementSystemResource\Pages;
use App\Models\ManagementSystem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ManagementSystemResource extends Resource
{
    protected static ?string $model = ManagementSystem::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Fondasi ISO';
    protected static ?string $modelLabel = 'Sistem Manajemen';
    protected static ?string $pluralModelLabel = 'Sistem Manajemen';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('Nama Sistem')->required(),
            Forms\Components\Select::make('standard_id')->label('Standar')->relationship('standard', 'code')->required()->searchable()->preload(),
            Forms\Components\Select::make('compliance_scope_id')->label('Lingkup')->relationship('scope', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('status')->label('Status')->options(['planning' => 'Perencanaan','implementation' => 'Implementasi','internal_audit' => 'Audit Internal','certification_ready' => 'Siap Sertifikasi','certified' => 'Tersertifikasi','suspended' => 'Ditangguhkan'])->required()->default('planning'),
            Forms\Components\DatePicker::make('target_certification_date')->label('Target Sertifikasi')->native(false),
            Forms\Components\DatePicker::make('certified_at')->label('Tanggal Sertifikasi')->native(false),
            Forms\Components\DatePicker::make('certificate_expires_at')->label('Sertifikat Berakhir')->native(false),
            Forms\Components\Textarea::make('description')->label('Catatan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Sistem Manajemen')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('standard.code')->label('Standar')->sortable(),
            Tables\Columns\TextColumn::make('scope.name')->label('Lingkup')->wrap(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('target_certification_date')->label('Target')->date('d/m/Y')->placeholder('-'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageManagementSystems::route('/')]; }
}
