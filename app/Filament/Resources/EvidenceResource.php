<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceResource\Pages;
use App\Models\Evidence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceResource extends Resource
{
    protected static ?string $model = Evidence::class;
    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';
    protected static ?string $navigationGroup = 'Dokumen & Bukti';
    protected static ?string $modelLabel = 'Evidence';
    protected static ?string $pluralModelLabel = 'Evidence';
    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('title')->label('Judul Evidence')->required(),
            Forms\Components\Select::make('type')->label('Jenis')->options(['record' => 'Rekaman','screenshot' => 'Screenshot','report' => 'Laporan','log' => 'Log','configuration' => 'Konfigurasi','other' => 'Lainnya'])->required(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\FileUpload::make('file_path')->label('File')->directory('iso/evidence')->downloadable()->openable(),
            Forms\Components\TextInput::make('external_url')->label('URL Eksternal')->url(),
            Forms\Components\DatePicker::make('captured_at')->label('Tanggal Evidence')->native(false),
            Forms\Components\DatePicker::make('expires_at')->label('Berlaku Sampai')->native(false),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif','expired' => 'Kedaluwarsa','archived' => 'Arsip'])->required(),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->placeholder('-'),
            Tables\Columns\TextColumn::make('title')->label('Evidence')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('captured_at')->label('Tanggal')->date('d/m/Y')->placeholder('-'),
            Tables\Columns\TextColumn::make('expires_at')->label('Berlaku Sampai')->date('d/m/Y')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageEvidences::route('/')]; }
}
