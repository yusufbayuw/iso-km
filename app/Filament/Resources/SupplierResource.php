<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Third Parties';
    protected static ?string $modelLabel = 'Supplier';
    protected static ?string $pluralModelLabel = 'Suppliers & Third Parties';
    protected static ?int $navigationSort = 190;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('Nama Supplier')->required(),
            Forms\Components\TextInput::make('category')->label('Kategori'),
            Forms\Components\Select::make('owner_id')->label('Owner Internal')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Select::make('criticality')->label('Criticality')->options(['low' => 'Low','medium' => 'Medium','high' => 'High','critical' => 'Critical'])->required()->default('medium'),
            Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Aktif','inactive' => 'Tidak Aktif','suspended' => 'Suspended','terminated' => 'Terminated'])->required()->default('active'),
            Forms\Components\TextInput::make('contact_name')->label('Kontak'),
            Forms\Components\TextInput::make('contact_email')->label('Email')->email(),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Select::make('managementSystems')->label('Sistem Manajemen')->relationship('managementSystems', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'code')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'code')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('evidences')->label('Evidence Supplier')->relationship('evidences', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('service_description')->label('Layanan / Produk')->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Supplier')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category')->label('Kategori')->placeholder('-'),
            Tables\Columns\TextColumn::make('criticality')->label('Criticality')->badge(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('assessments_count')->label('Assessment')->counts('assessments'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageSuppliers::route('/')]; }
}
