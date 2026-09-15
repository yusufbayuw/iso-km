<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Dokumen & Bukti';
    protected static ?string $modelLabel = 'Dokumen';
    protected static ?string $pluralModelLabel = 'Document Control';
    protected static ?int $navigationSort = 80;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kode Dokumen')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('title')->label('Judul')->required(),
            Forms\Components\Select::make('type')->label('Jenis')->options(['policy' => 'Kebijakan','manual' => 'Manual','procedure' => 'Prosedur/SOP','work_instruction' => 'Instruksi Kerja','form' => 'Formulir','record' => 'Rekaman','external' => 'Dokumen Eksternal'])->required(),
            Forms\Components\Select::make('owner_id')->label('Document Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\Select::make('confidentiality')->label('Klasifikasi')->options(['public' => 'Publik','internal' => 'Internal','confidential' => 'Rahasia','restricted' => 'Terbatas'])->required(),
            Forms\Components\Select::make('status')->label('Status')->options(['draft' => 'Draft','review' => 'Review','approved' => 'Disetujui','effective' => 'Berlaku','obsolete' => 'Obsolete'])->required(),
            Forms\Components\TextInput::make('review_frequency_months')->label('Interval Review (bulan)')->numeric()->minValue(1)->default(12),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Select::make('managementSystems')->label('Sistem Manajemen')->relationship('managementSystems', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
            Forms\Components\Repeater::make('revisions')->label('Riwayat Revisi')->relationship()->schema([
                Forms\Components\TextInput::make('revision')->label('Revisi')->required(),
                Forms\Components\FileUpload::make('file_path')->label('File')->directory('iso/documents')->downloadable()->openable(),
                Forms\Components\Textarea::make('change_summary')->label('Ringkasan Perubahan')->columnSpanFull(),
                Forms\Components\DatePicker::make('effective_at')->label('Mulai Berlaku')->native(false),
                Forms\Components\DatePicker::make('obsolete_at')->label('Obsolete')->native(false),
                Forms\Components\Toggle::make('is_current')->label('Revisi Aktif'),
            ])->columns(2)->columnSpanFull()->collapsible()->itemLabel(fn (array $state): ?string => $state['revision'] ?? null),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('Dokumen')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('currentRevision.revision')->label('Revisi')->placeholder('-'),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageDocuments::route('/')]; }
}
