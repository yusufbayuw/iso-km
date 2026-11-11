<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceResource\Pages;
use App\Models\Evidence;
use App\Services\EvidenceLifecycleService;
use Filament\Forms;
use Filament\Forms\Form;
use App\Models\ManagementSystem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EvidenceResource extends Resource
{
    protected static ?string $model = Evidence::class;
    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';
    protected static ?string $navigationGroup = 'Assurance';
    protected static ?string $modelLabel = 'Evidence';
    protected static ?string $pluralModelLabel = 'Evidence Register';
    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->live()->searchable()->preload()->required(),
            Forms\Components\TextInput::make('title')->label('Judul Evidence')->required()->columnSpanFull(),
            Forms\Components\Select::make('type')->label('Jenis')->options(['record' => 'Rekaman','screenshot' => 'Screenshot','report' => 'Laporan','log' => 'Log','configuration' => 'Konfigurasi','other' => 'Lainnya'])->required(),
            Forms\Components\TextInput::make('source')->label('Sumber')->placeholder('Sistem, unit, vendor, atau proses'),
            Forms\Components\Select::make('confidentiality')->label('Klasifikasi')->options(['public' => 'Publik','internal' => 'Internal','confidential' => 'Rahasia','restricted' => 'Terbatas'])->default('internal')->required(),
            Forms\Components\Select::make('owner_id')->label('Owner')->relationship('owner', 'name')->searchable()->preload(),
            Forms\Components\FileUpload::make('file_path')->label('File')->directory('iso/evidence')->downloadable()->openable(),
            Forms\Components\TextInput::make('external_url')->label('URL Eksternal')->url(),
            Forms\Components\DatePicker::make('captured_at')->label('Tanggal Evidence')->native(false),
            Forms\Components\DatePicker::make('valid_from')->label('Valid Sejak')->native(false),
            Forms\Components\DatePicker::make('expires_at')->label('Berlaku Sampai')->native(false),
            Forms\Components\Select::make('status')->label('Lifecycle')->options(['active' => 'Aktif','expired' => 'Kedaluwarsa','archived' => 'Arsip'])->default('active')->required(),
            Forms\Components\Placeholder::make('verification_status_display')->label('Status Verifikasi')->content(fn (?Evidence $record): string => $record?->verification_status ?? 'pending'),
            Forms\Components\Select::make('requirements')->label('Requirement Terkait')->relationship('requirements', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Select::make('controls')->label('Control Terkait')->relationship('controls', 'title', modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                ->where('standard_id', ManagementSystem::query()->whereKey($get('management_system_id'))->value('standard_id') ?? -1))->multiple()->searchable()->preload()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
            Forms\Components\Placeholder::make('verification_notes_display')->label('Catatan Verifikasi')->content(fn (?Evidence $record): string => $record?->verification_notes ?: '-')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->placeholder('-'), Tables\Columns\TextColumn::make('title')->label('Evidence')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('type')->label('Jenis')->badge(), Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('captured_at')->label('Tanggal')->date('d/m/Y')->placeholder('-'), Tables\Columns\TextColumn::make('expires_at')->label('Berlaku Sampai')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('verification_status')->label('Assurance')->badge(), Tables\Columns\TextColumn::make('status')->label('Lifecycle')->badge(),
        ])->filters([
            Tables\Filters\SelectFilter::make('verification_status')->options(['pending' => 'Pending','verified' => 'Verified','rejected' => 'Rejected']),
            Tables\Filters\Filter::make('expired')->query(fn (Builder $query): Builder => $query->whereNotNull('expires_at')->whereDate('expires_at', '<', today())),
            Tables\Filters\Filter::make('expiring')->query(fn (Builder $query): Builder => $query->whereBetween('expires_at', [today(), today()->addDays(30)])),
        ])->actions([
            Tables\Actions\Action::make('verify')->label('Verify')->icon('heroicon-o-shield-check')->color('success')->visible(fn (Evidence $record): bool => $record->verification_status !== 'verified' && (auth()->user()?->can('evidence.verify') ?? false))->form([Forms\Components\Textarea::make('notes')->label('Catatan Verifikasi')])->requiresConfirmation()->action(fn (Evidence $record, array $data) => app(EvidenceLifecycleService::class)->verifyEvidence($record, auth()->user(), $data['notes'] ?? null)),
            Tables\Actions\Action::make('reject')->label('Reject')->icon('heroicon-o-x-circle')->color('danger')->visible(fn (Evidence $record): bool => $record->verification_status !== 'rejected' && (auth()->user()?->can('evidence.verify') ?? false))->form([Forms\Components\Textarea::make('notes')->label('Alasan')->required()])->requiresConfirmation()->action(fn (Evidence $record, array $data) => app(EvidenceLifecycleService::class)->rejectEvidence($record, auth()->user(), $data['notes'])),
            Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageEvidences::route('/')]; }
}
