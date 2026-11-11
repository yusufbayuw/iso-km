<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierAssessmentResource\Pages;
use App\Models\SupplierAssessment;
use App\Services\ActionItemSyncService;
use App\Services\SupplierAssessmentWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierAssessmentResource extends Resource
{
    use \App\Filament\Resources\Concerns\ScopesByManagementSystem;

    protected static ?string $model = SupplierAssessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Third Parties';
    protected static ?string $modelLabel = 'Assessment Supplier';
    protected static ?string $pluralModelLabel = 'Supplier Assessments';
    protected static ?int $navigationSort = 200;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('managementSystem', 'name')->required()->searchable()->preload(),
            Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            Forms\Components\Select::make('evidence_id')->label('Evidence Assessment')->relationship('evidence', 'title')->searchable()->preload()->columnSpanFull(),
            Forms\Components\Placeholder::make('workflow_status')->label('Status Workflow')->content(fn (?SupplierAssessment $record): string => $record?->status ?? 'planned'),
            Forms\Components\Placeholder::make('risk_display')->label('Risk Rating')->content(fn (?SupplierAssessment $record): string => $record?->risk_rating ?? 'medium'),
            Forms\Components\Textarea::make('summary')->label('Ringkasan Assessment')->disabled()->dehydrated(false)->columnSpanFull(),
            Forms\Components\Textarea::make('required_actions')->label('Tindak Lanjut')->disabled()->dehydrated(false)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('supplier.code')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('managementSystem.code')->label('Sistem')->sortable(),
            Tables\Columns\TextColumn::make('assessor.name')->label('Assessor')->placeholder('-'),
            Tables\Columns\TextColumn::make('assessed_at')->label('Assessment')->date('d/m/Y')->placeholder('-')->sortable(),
            Tables\Columns\TextColumn::make('score')->label('Score')->placeholder('-'),
            Tables\Columns\TextColumn::make('risk_rating')->label('Risk')->badge(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('next_review_at')->label('Review')->date('d/m/Y')->placeholder('-')->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (SupplierAssessment $record): bool => $record->status === 'planned'),
            Tables\Actions\Action::make('request')->label('Request')->icon('heroicon-o-paper-airplane')->visible(fn (SupplierAssessment $record): bool => $record->status === 'planned' && (auth()->user()?->can('supplier.assess') ?? false))->requiresConfirmation()->action(function (SupplierAssessment $record): void {
                app(SupplierAssessmentWorkflowService::class)->request($record, auth()->user());
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Assessment diminta')->send();
            }),
            Tables\Actions\Action::make('start')->label('Mulai')->icon('heroicon-o-play')->visible(fn (SupplierAssessment $record): bool => in_array($record->status, ['planned', 'requested'], true) && (auth()->user()?->can('supplier.assess') ?? false))->action(function (SupplierAssessment $record): void {
                app(SupplierAssessmentWorkflowService::class)->start($record, auth()->user());
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Assessment dimulai')->send();
            }),
            Tables\Actions\Action::make('submit')->label('Submit')->icon('heroicon-o-arrow-up-tray')->visible(fn (SupplierAssessment $record): bool => in_array($record->status, ['requested', 'in_progress'], true) && (auth()->user()?->can('supplier.assess') ?? false))->form([
                Forms\Components\Textarea::make('summary')->label('Ringkasan Assessment')->required(),
                Forms\Components\Select::make('evidence_id')->label('Evidence')->relationship('evidence', 'title')->searchable()->preload(),
            ])->action(function (SupplierAssessment $record, array $data): void {
                app(SupplierAssessmentWorkflowService::class)->submit($record, auth()->user(), $data['summary'], $data['evidence_id'] ?? null);
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Assessment dikirim untuk review')->send();
            }),
            Tables\Actions\Action::make('review')->label('Review')->icon('heroicon-o-magnifying-glass')->visible(fn (SupplierAssessment $record): bool => $record->status === 'submitted' && (auth()->user()?->can('supplier.review') ?? false))->form([
                Forms\Components\Textarea::make('notes')->label('Catatan Review'),
            ])->action(function (SupplierAssessment $record, array $data): void {
                app(SupplierAssessmentWorkflowService::class)->beginReview($record, auth()->user(), $data['notes'] ?? null);
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Review assessment dimulai')->send();
            }),
            Tables\Actions\Action::make('decide')->label('Keputusan')->icon('heroicon-o-scale')->visible(fn (SupplierAssessment $record): bool => $record->status === 'under_review' && (auth()->user()?->can('supplier.decide') ?? false))->form([
                Forms\Components\Select::make('decision')->label('Keputusan')->options(['accepted' => 'Accepted','conditional' => 'Conditional','rejected' => 'Rejected'])->required(),
                Forms\Components\Select::make('risk_rating')->label('Risk Rating')->options(['low' => 'Low','medium' => 'Medium','high' => 'High','critical' => 'Critical'])->required(),
                Forms\Components\TextInput::make('score')->label('Score')->numeric()->minValue(0)->maxValue(100),
                Forms\Components\Textarea::make('required_actions')->label('Required Actions'),
                Forms\Components\DatePicker::make('next_review_at')->label('Review Berikutnya')->native(false),
            ])->action(function (SupplierAssessment $record, array $data): void {
                app(SupplierAssessmentWorkflowService::class)->decide($record, auth()->user(), $data['decision'], $data['risk_rating'], isset($data['score']) && $data['score'] !== '' ? (float) $data['score'] : null, $data['required_actions'] ?? null, $data['next_review_at'] ?? null);
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Keputusan assessment disimpan')->send();
            }),
            Tables\Actions\Action::make('verifyTreatment')->label('Verifikasi Treatment')->icon('heroicon-o-shield-check')->color('success')->visible(fn (SupplierAssessment $record): bool => $record->status === 'conditional' && (auth()->user()?->can('supplier.verify_treatment') ?? false))->form([
                Forms\Components\Textarea::make('notes')->label('Bukti / Catatan Verifikasi')->required(),
            ])->action(function (SupplierAssessment $record, array $data): void {
                app(SupplierAssessmentWorkflowService::class)->verifyTreatment($record, auth()->user(), $data['notes']);
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Treatment supplier terverifikasi')->send();
            }),
            Tables\Actions\Action::make('close')->label('Close')->icon('heroicon-o-lock-closed')->visible(fn (SupplierAssessment $record): bool => in_array($record->status, ['accepted', 'rejected'], true) && (auth()->user()?->can('supplier.decide') ?? false))->requiresConfirmation()->action(function (SupplierAssessment $record): void {
                app(SupplierAssessmentWorkflowService::class)->close($record, auth()->user());
                app(ActionItemSyncService::class)->sync();
                Notification::make()->success()->title('Assessment ditutup')->send();
            }),
            Tables\Actions\DeleteAction::make()->visible(fn (SupplierAssessment $record): bool => $record->status === 'planned'),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageSupplierAssessments::route('/')]; }
}
