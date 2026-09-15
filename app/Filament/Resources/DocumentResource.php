<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Services\DocumentWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
            Forms\Components\Placeholder::make('workflow_status')->label('Status Dokumen')->content(fn (?Document $record): string => $record?->status ?? 'draft'),
            Forms\Components\TextInput::make('review_frequency_months')->label('Interval Review (bulan)')->numeric()->minValue(1)->default(12),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false)->disabled(),
            Forms\Components\Select::make('managementSystems')->label('Sistem Manajemen')->relationship('managementSystems', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'title')->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
            Forms\Components\Repeater::make('revisions')->label('Riwayat Revisi')->relationship()->schema([
                Forms\Components\TextInput::make('revision')->label('Revisi')->required(),
                Forms\Components\Placeholder::make('status_display')->label('Status')->content(fn (?DocumentRevision $record): string => $record?->status ?? 'draft'),
                Forms\Components\FileUpload::make('file_path')->label('File')->directory('iso/documents')->downloadable()->openable(),
                Forms\Components\Select::make('reviewer_id')->label('Reviewer')->relationship('reviewer', 'name')->searchable()->preload(),
                Forms\Components\Select::make('approver_id')->label('Approver')->relationship('approver', 'name')->searchable()->preload(),
                Forms\Components\Textarea::make('change_summary')->label('Ringkasan Perubahan')->columnSpanFull(),
                Forms\Components\Textarea::make('review_notes')->label('Catatan Review')->disabled()->columnSpanFull(),
            ])->columns(2)->columnSpanFull()->collapsible()->itemLabel(fn (array $state): ?string => $state['revision'] ?? null),
            Forms\Components\Repeater::make('distributions')->label('Distribusi & Acknowledgement')->relationship()->schema([
                Forms\Components\Select::make('user_id')->label('Pengguna')->relationship('user', 'name')->required()->searchable()->preload(),
                Forms\Components\DatePicker::make('acknowledgement_due_at')->label('Batas Acknowledgement')->native(false),
                Forms\Components\Placeholder::make('distribution_status')->label('Status')->content(fn ($record): string => $record?->status ?? 'pending'),
            ])->columns(2)->columnSpanFull()->collapsible(),
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
            Tables\Columns\TextColumn::make('currentRevision.revision')->label('Revisi Efektif')->placeholder('-'),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('submitReview')
                ->label('Ajukan Review')->icon('heroicon-o-paper-airplane')
                ->visible(fn (Document $record): bool => auth()->user()?->can('document.submit_review') && in_array(self::latestRevision($record)?->status, ['draft', 'rejected'], true))
                ->requiresConfirmation()
                ->action(function (Document $record): void {
                    app(DocumentWorkflowService::class)->submitForReview(self::latestRevision($record), auth()->user());
                    Notification::make()->success()->title('Dokumen diajukan untuk review')->send();
                }),
            Tables\Actions\Action::make('review')
                ->label('Setujui Review')->icon('heroicon-o-check-circle')
                ->visible(fn (Document $record): bool => auth()->user()?->can('document.review') && self::latestRevision($record)?->status === 'in_review')
                ->form([Forms\Components\Textarea::make('notes')->label('Catatan Review')])
                ->action(function (Document $record, array $data): void {
                    app(DocumentWorkflowService::class)->review(self::latestRevision($record), auth()->user(), $data['notes'] ?? null);
                    Notification::make()->success()->title('Review selesai')->send();
                }),
            Tables\Actions\Action::make('rejectReview')
                ->label('Kembalikan')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (Document $record): bool => auth()->user()?->can('document.review') && in_array(self::latestRevision($record)?->status, ['in_review', 'reviewed'], true))
                ->form([Forms\Components\Textarea::make('notes')->label('Alasan / Catatan')->required()])
                ->action(function (Document $record, array $data): void {
                    app(DocumentWorkflowService::class)->reject(self::latestRevision($record), auth()->user(), $data['notes']);
                    Notification::make()->warning()->title('Dokumen dikembalikan')->send();
                }),
            Tables\Actions\Action::make('approve')
                ->label('Approve')->icon('heroicon-o-shield-check')
                ->visible(fn (Document $record): bool => auth()->user()?->can('document.approve') && self::latestRevision($record)?->status === 'reviewed')
                ->requiresConfirmation()
                ->action(function (Document $record): void {
                    app(DocumentWorkflowService::class)->approve(self::latestRevision($record), auth()->user());
                    Notification::make()->success()->title('Dokumen disetujui')->send();
                }),
            Tables\Actions\Action::make('makeEffective')
                ->label('Berlakukan')->icon('heroicon-o-bolt')->color('success')
                ->visible(fn (Document $record): bool => auth()->user()?->can('document.make_effective') && self::latestRevision($record)?->status === 'approved')
                ->form([Forms\Components\DatePicker::make('effective_at')->label('Tanggal Berlaku')->native(false)->default(today())])
                ->action(function (Document $record, array $data): void {
                    app(DocumentWorkflowService::class)->makeEffective(self::latestRevision($record), auth()->user(), $data['effective_at'] ?? null);
                    Notification::make()->success()->title('Revisi diberlakukan')->send();
                }),
            Tables\Actions\Action::make('obsolete')
                ->label('Obsolete')->icon('heroicon-o-archive-box')->color('danger')
                ->visible(fn (Document $record): bool => auth()->user()?->can('document.obsolete') && $record->currentRevision?->status === 'effective')
                ->requiresConfirmation()
                ->action(function (Document $record): void {
                    app(DocumentWorkflowService::class)->obsolete($record->currentRevision, auth()->user());
                    Notification::make()->success()->title('Dokumen dinyatakan obsolete')->send();
                }),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    private static function latestRevision(Document $record): ?DocumentRevision
    {
        return $record->revisions()->latest('id')->first();
    }

    public static function getPages(): array { return ['index' => Pages\ManageDocuments::route('/')]; }
}
