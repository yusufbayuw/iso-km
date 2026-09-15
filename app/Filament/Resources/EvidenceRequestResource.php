<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EvidenceRequestResource\Pages;
use App\Models\Evidence;
use App\Models\EvidenceRequest;
use App\Services\EvidenceLifecycleService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EvidenceRequestResource extends Resource
{
    protected static ?string $model = EvidenceRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Pekerjaan Saya';
    protected static ?string $navigationLabel = 'Evidence Requests';
    protected static ?string $modelLabel = 'Evidence Request';
    protected static ?string $pluralModelLabel = 'Evidence Requests';
    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['schedule', 'owner', 'managementSystem', 'evidence']);
        $user = auth()->user();
        if (! $user?->can('evidence_request.view_all')) {
            $query->where('owner_id', $user?->id);
        }
        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')->columns([
            Tables\Columns\TextColumn::make('schedule.code')->label('Jadwal')->placeholder('Manual'),
            Tables\Columns\TextColumn::make('schedule.title')->label('Evidence')->wrap()->placeholder('-'),
            Tables\Columns\TextColumn::make('period_label')->label('Periode')->placeholder('-'),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner')->placeholder('-'),
            Tables\Columns\TextColumn::make('due_at')->label('Due')->date('d/m/Y')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('evidence.title')->label('Submitted Evidence')->wrap()->placeholder('-'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['requested' => 'Requested','submitted' => 'Submitted','overdue' => 'Overdue','verified' => 'Verified','waived' => 'Waived']),
            Tables\Filters\Filter::make('overdue')->query(fn (Builder $query): Builder => $query->whereNotIn('status', ['verified', 'waived'])->whereDate('due_at', '<', today())),
        ])->actions([
            Tables\Actions\Action::make('submit')->label('Submit Evidence')->icon('heroicon-o-arrow-up-tray')->visible(fn (EvidenceRequest $record): bool => in_array($record->status, ['requested', 'overdue'], true) && ($record->owner_id === auth()->id() || auth()->user()?->can('evidence.manage')))->form([
                Forms\Components\TextInput::make('title')->label('Judul Evidence')->required(),
                Forms\Components\FileUpload::make('file_path')->label('File')->directory('iso/evidence')->downloadable()->openable(),
                Forms\Components\TextInput::make('external_url')->label('URL Eksternal')->url(),
                Forms\Components\DatePicker::make('captured_at')->label('Tanggal Evidence')->default(today())->required()->native(false),
                Forms\Components\Textarea::make('description')->label('Deskripsi'),
            ])->action(function (EvidenceRequest $record, array $data): void {
                $evidence = Evidence::create(['management_system_id' => $record->management_system_id,'owner_id' => $record->owner_id ?: auth()->id(),'title' => $data['title'],'type' => $record->schedule?->evidence_type ?: 'record','file_path' => $data['file_path'] ?? null,'external_url' => $data['external_url'] ?? null,'captured_at' => $data['captured_at'],'description' => $data['description'] ?? null,'status' => 'active','verification_status' => 'pending']);
                app(EvidenceLifecycleService::class)->submit($record, $evidence, auth()->user());
            }),
            Tables\Actions\Action::make('verify')->label('Verify')->icon('heroicon-o-shield-check')->color('success')->visible(fn (EvidenceRequest $record): bool => in_array($record->status, ['submitted', 'overdue'], true) && filled($record->evidence_id) && auth()->user()?->can('evidence.verify'))->form([Forms\Components\Textarea::make('notes')->label('Catatan Verifikasi')])->requiresConfirmation()->action(fn (EvidenceRequest $record, array $data) => app(EvidenceLifecycleService::class)->verify($record, auth()->user(), $data['notes'] ?? null)),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageEvidenceRequests::route('/')]; }
}
