<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedExportTemplateResource\Pages;
use App\Models\GuidedExportTemplate;
use App\Services\GuidedWorkbookMappingService;
use App\Services\GuidedSystemAccessService;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GuidedExportTemplateResource extends Resource
{
    protected static ?string $model = GuidedExportTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Template XLSX';
    protected static ?int $navigationSort = 25;

    public static function canViewAny(): bool
    {
        if (! config('iso-km.guided_assessment_enabled', false) || ! ($user = auth()->user())
            || ! $user->can('guided.template.manage')) {
            return false;
        }
        return $user->can('guided.template.manage_global')
            || app(GuidedSystemAccessService::class)->accessibleSystems($user)->exists();
    }
    public static function canCreate(): bool { return self::canViewAny(); }
    public static function canView($record): bool
    {
        return self::canViewAny()
            && app(GuidedSystemAccessService::class)->canManageTemplate(auth()->user(), $record);
    }
    public static function canEdit($record): bool { return self::canView($record); }
    public static function canDelete($record): bool { return self::canEdit($record) && ! $record->exports()->exists(); }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (! $user || ! self::canViewAny()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        $access = app(GuidedSystemAccessService::class);
        return parent::getEloquentQuery()->where(function (Builder $query) use ($user, $access): void {
            $query->whereIn('management_system_id', $access->accessibleSystems($user)->select('id'));
            if ($user->can('guided.template.manage_global')) {
                $query->orWhereNull('management_system_id');
            }
        });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')
                ->relationship('system', 'name', modifyQueryUsing: fn (Builder $query) => $query
                    ->whereIn('id', app(GuidedSystemAccessService::class)
                        ->accessibleSystems(auth()->user())->select('id')))
                ->required(fn () => ! (auth()->user()?->can('guided.template.manage_global') ?? false))
                ->searchable()->preload()
                ->helperText('Template global hanya dapat dibuat/diedit oleh pemegang izin khusus.'),
            Forms\Components\TextInput::make('name')->label('Nama Template')->required(),
            Forms\Components\Select::make('report_type')->label('Jenis Laporan')->options(['custom' => 'Laporan Kustom', 'gap_analysis' => 'GAP Analysis Lengkap'])->default('custom')->required()
                ->helperText('GAP Analysis lengkap mensyaratkan pemetaan semua kriteria yang dinilai, bukan hanya ringkasan.'),
            Forms\Components\FileUpload::make('file_path')->label('File XLSX')->disk('local')
                ->directory('iso/guided/templates')->visibility('private')->maxSize(20480)
                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->required()
                ->helperText('File asli dipertahankan; hanya sel yang dipetakan akan diisi.')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Hidden::make('created_by')->default(fn () => auth()->id()),
            Forms\Components\Repeater::make('mappings')->label('Pemetaan Sel')->schema([
                Forms\Components\TextInput::make('sheet')->label('Nama Worksheet')->required(),
                Forms\Components\TextInput::make('cell')->label('Alamat Sel (contoh E2)')->required(),
                Forms\Components\Select::make('source')->label('Sumber')->options([
                    'assessment' => 'Asesmen', 'requirement' => 'Requirement', 'control' => 'Control',
                    'risk_index' => 'Risiko (Urutan)', 'asset_index' => 'Aset Informasi (Urutan)',
                    'activity_index' => 'Aktivitas Informasi (Urutan)',
                ])->required(),
                Forms\Components\TextInput::make('code')->label('Kode atau Nomor Urut')
                    ->helperText('Requirement/control: kode standar. Risiko/aset/aktivitas: nomor urut 1,2,3... Kosong jika Asesmen.'),
                Forms\Components\Select::make('field')->label('Data')->options([
                    'name' => 'Nama Asesmen', 'management_system' => 'Sistem Manajemen',
                    'scope' => 'Ruang Lingkup', 'unit_name' => 'Unit / Bagian',
                    'scope_description' => 'Deskripsi Ruang Lingkup',
                    'organization_name' => 'Nama Organisasi',
                    'assessor_names' => 'Nama Asesor', 'assessor_role' => 'Jabatan Asesor',
                    'date' => 'Tanggal Asesmen',
                    'category' => 'Kategori Aset / Risiko', 'title' => 'Judul',
                    'description' => 'Deskripsi / Dampak',
                    'in_scope' => 'Dikelola / Dilakukan',
                    'classification' => 'Klasifikasi Aset',
                    'related_controls' => 'Kontrol Terkait',
                    'notes' => 'Catatan Aset / Aktivitas',
                    'control_1' => 'Kontrol Risiko 1', 'control_2' => 'Kontrol Risiko 2',
                    'control_3' => 'Kontrol Risiko 3',
                    'likelihood' => 'Kemungkinan (1–5)', 'impact' => 'Dampak (1–5)',
                    'treatment_plan' => 'Rencana Tindak Lanjut',
                    'status' => 'Status untuk Excel', 'raw_status' => 'Status Teknis',
                    'rationale' => 'Alasan Penilaian', 'summary' => 'Hasil Konsolidasi',
                    'risk_score' => 'Skor Risiko', 'evidence_refs' => 'Kode Evidence',
                    'notes_and_evidence' => 'Catatan dan Bukti', 'na_justification' => 'Alasan Tidak Berlaku',
                    'approved_at' => 'Tanggal Disetujui', 'applicable' => 'Applicability',
                ])->required(),
                Forms\Components\Toggle::make('required')->label('Wajib tersedia')->default(true),
            ])->columns(2)->defaultItems(0)->columnSpanFull()
                ->helperText('Mapping disusun berdasarkan kode master standar, bukan posisi tetap dari satu organisasi.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Template')->searchable(),
            Tables\Columns\TextColumn::make('system.name')->label('Sistem')->placeholder('Global'),
            Tables\Columns\TextColumn::make('is_active')->label('Aktif')->badge(),
            Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d/m/Y H:i'),
        ])->actions([
            Tables\Actions\Action::make('detect_mapping')->label('Deteksi Kolom')->icon('heroicon-o-magnifying-glass')
                ->requiresConfirmation()
                ->visible(fn (GuidedExportTemplate $record) => self::canEdit($record)
                    && $record->is_active && $record->management_system_id !== null)
                ->modalDescription('Sistem membaca kode persyaratan/kontrol dan kolom status/evidence. Pemetaan yang ada akan diganti. Periksa hasil sebelum ekspor final.')
                ->action(function (GuidedExportTemplate $record): void {
                    $result = app(GuidedWorkbookMappingService::class)->suggest($record);
                    if (! count($result['mappings'])) {
                        Notification::make()->warning()->title('Tidak ada kolom yang dapat dipetakan')->send();
                        return;
                    }
                    $record->update(['mappings' => $result['mappings']]);
                    Notification::make()->success()->title($result['recognized_targets'].' item terpetakan')
                        ->body(count($result['warnings']).' catatan. Periksa pemetaan dan katalog standar sebelum ekspor final.')->send();
                }),
            Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageGuidedExportTemplates::route('/')]; }
}
