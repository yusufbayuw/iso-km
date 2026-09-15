<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplianceObligationResource\Pages;
use App\Models\ComplianceObligation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ComplianceObligationResource extends Resource
{
    protected static ?string $model = ComplianceObligation::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Performance & Review';
    protected static ?string $modelLabel = 'Kewajiban Kepatuhan';
    protected static ?string $pluralModelLabel = 'Compliance Obligations';
    protected static ?int $navigationSort = 150;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Judul Kewajiban')->required()->columnSpanFull(),
            Forms\Components\Select::make('type')->label('Jenis')->options(['law' => 'Undang-undang','regulation' => 'Regulasi','contract' => 'Kontrak','policy' => 'Kebijakan Internal','other' => 'Lainnya'])->required()->default('regulation'),
            Forms\Components\TextInput::make('jurisdiction')->label('Yurisdiksi'),
            Forms\Components\TextInput::make('reference')->label('Referensi / Nomor'),
            Forms\Components\TextInput::make('source_url')->label('Sumber URL')->url(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Select::make('status')->label('Status')->options(['applicable' => 'Applicable','under_review' => 'Under Review','not_applicable' => 'Not Applicable','superseded' => 'Superseded'])->required()->default('applicable'),
            Forms\Components\DatePicker::make('last_reviewed_at')->label('Terakhir Direview')->native(false),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Select::make('managementSystems')->label('Sistem Manajemen Terkait')->relationship('managementSystems', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Kewajiban / Implikasi Kepatuhan')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Kewajiban')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(),
            Tables\Columns\TextColumn::make('jurisdiction')->label('Yurisdiksi')->placeholder('-'),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageComplianceObligations::route('/')]; }
}
