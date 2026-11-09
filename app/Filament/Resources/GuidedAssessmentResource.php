<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedAssessmentResource\Pages;
use App\Models\GuidedAssessment;
use App\Models\GuidedExportTemplate;
use App\Services\GuidedWorkbookExportService;
use Illuminate\Support\Facades\Storage;
use App\Models\GuidedQuestion;
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedAssessmentResource extends Resource
{
    protected static ?string $model = GuidedAssessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Periode Asesmen';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool { return auth()->user()?->can('guided.assessment.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->can('guided.assessment.manage') ?? false; }
    public static function canEdit($record): bool { return self::canCreate() && $record->isMember(auth()->user(), ['coordinator']) && ! $record->locked_at; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('memberships', fn ($q) => $q->where('user_id', auth()->id()));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('management_system_id')->label('Sistem Manajemen')->relationship('system', 'name')->required()->searchable()->preload()->disabledOn('edit'),
            Forms\Components\Select::make('guided_question_bank_id')->label('Bank Pertanyaan')->relationship('bank', 'name', modifyQueryUsing: fn ($q) => $q->where('status', 'published'))->required()->searchable()->preload()->disabledOn('edit')
                ->helperText('Pilih bank pertanyaan terbit dengan standar yang sama.'),
            Forms\Components\TextInput::make('name')->label('Nama Asesmen')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('high_risk_threshold')->label('Ambang Risiko Tinggi')->numeric()->minValue(1)->maxValue(25)->default(12)->required(),
            Forms\Components\TextInput::make('critical_risk_threshold')->label('Ambang Risiko Kritis')->numeric()->minValue(1)->maxValue(25)->default(20)->required(),
            Forms\Components\Hidden::make('created_by')->default(fn () => auth()->id()),
            Forms\Components\Repeater::make('memberships')->label('Partisipan dan Kewenangan')->relationship()->schema([
                Forms\Components\Select::make('user_id')->label('Pengguna')->relationship('user', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('role')->label('Kewenangan')->options([
                    'coordinator' => 'Koordinator', 'respondent' => 'Responden', 'consolidator' => 'Petugas Konsolidasi',
                    'reviewer' => 'Reviewer Evidence', 'assessor' => 'Asesor',
                    'risk_owner' => 'Pemilik Risiko', 'management' => 'Persetujuan Manajemen',
                ])->required(),
            ])->columns(2)->defaultItems(0)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Asesmen')->searchable(),
            Tables\Columns\TextColumn::make('system.name')->label('Sistem')->wrap(),
            Tables\Columns\TextColumn::make('bank.name')->label('Bank')->wrap(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('assignments_count')->label('Tugas')->counts('assignments'),
            Tables\Columns\TextColumn::make('locked_at')->label('Dikunci')->dateTime('d/m/Y H:i')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('export')->label('Unduh XLSX')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (GuidedAssessment $record) => $record->isMember(auth()->user(), ['coordinator'])
                    && (auth()->user()?->can('guided.report.export') ?? false))
                ->form([
                    Forms\Components\Select::make('template_id')->label('Template Ekspor')->required()->searchable()
                        ->options(fn (GuidedAssessment $record) => GuidedExportTemplate::query()
                            ->where('is_active', true)
                            ->where(fn ($q) => $q->whereNull('management_system_id')->orWhere('management_system_id', $record->management_system_id))
                            ->pluck('name', 'id')->all()),
                ])->action(function (GuidedAssessment $record, array $data) {
                    $path = app(GuidedWorkbookExportService::class)->export(
                        $record, GuidedExportTemplate::findOrFail($data['template_id']), auth()->user(),
                        $record->status === 'locked'
                    );
                    return response()->download(Storage::disk('local')->path($path), 'Assessment-'.$record->id.($record->status === 'locked' ? '-FINAL' : '-DRAFT').'.xlsx');
                }),
            Tables\Actions\Action::make('assign')->label('Tugaskan')->icon('heroicon-o-user-plus')
                ->visible(fn (GuidedAssessment $record) => self::canEdit($record))
                ->form([
                    Forms\Components\Select::make('question_id')->label('Pertanyaan')->options(fn (GuidedAssessment $record) => $record->bank->questions()->pluck('prompt', 'id')->all())->searchable()->required(),
                    Forms\Components\Select::make('user_id')->label('Responden')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                    Forms\Components\DatePicker::make('due_at')->label('Target Selesai'),
                ])->action(function (GuidedAssessment $record, array $data): void {
                    app(GuidedAssessmentWorkflowService::class)->assign($record, GuidedQuestion::findOrFail($data['question_id']), User::findOrFail($data['user_id']), auth()->user(), $data['due_at'] ?? null);
                    Notification::make()->success()->title('Pertanyaan ditugaskan')->send();
                }),
            Tables\Actions\Action::make('lock')->label('Kunci Final')->icon('heroicon-o-lock-closed')->color('warning')->requiresConfirmation()
                ->visible(fn (GuidedAssessment $record) => self::canEdit($record))
                ->action(function (GuidedAssessment $record): void {
                    app(GuidedAssessmentWorkflowService::class)->lock($record, auth()->user());
                    Notification::make()->success()->title('Asesmen dikunci')->send();
                }),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ManageGuidedAssessments::route('/')]; }
}
