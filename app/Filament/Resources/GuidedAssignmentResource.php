<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedAssignmentResource\Pages;
use App\Models\Evidence;
use App\Models\GuidedAssignment;
use App\Services\GuidedAssessmentWorkflowService;
use App\Services\GuidedEvidenceAccessService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedAssignmentResource extends Resource
{
    protected static ?string $model = GuidedAssignment::class;
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Wawancara Saya';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool { return config('iso-km.guided_assessment_enabled', false) && (auth()->user()?->can('guided.answer.submit') ?? false); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id())->with(['assessment', 'question']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('assessment.name')->label('Asesmen')->wrap(),
            Tables\Columns\TextColumn::make('question.code')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('question.prompt')->label('Pertanyaan')->wrap()->limit(110),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('answers_count')->label('Jawaban Terkirim')->counts('answers'),
            Tables\Columns\TextColumn::make('due_at')->label('Target')->date('d/m/Y')->placeholder('-'),
        ])->actions([
            Tables\Actions\Action::make('answer')->label('Kirim Jawaban')->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn (GuidedAssignment $record) => ! $record->assessment->locked_at)
                ->form([
                    Forms\Components\Placeholder::make('help')->label('Petunjuk')->content(fn (GuidedAssignment $record) => $record->question->help_text ?: $record->question->prompt),
                    Forms\Components\TextInput::make('context_key')->label('Konteks / Sistem / Aset')->default('general')->required()
                        ->helperText('Gunakan konteks berbeda untuk jawaban atas objek yang berbeda.'),
                    Forms\Components\Select::make('choice')->label('Pilihan Jawaban')
                        ->options(fn (GuidedAssignment $record) => array_combine($record->question->answer_options ?? [], $record->question->answer_options ?? []) ?: [])
                        ->visible(fn (GuidedAssignment $record) => $record->question->answer_type === 'single_choice')
                        ->required(fn (GuidedAssignment $record) => $record->question->answer_type === 'single_choice'),
                    Forms\Components\TextInput::make('value')->label('Jawaban')
                        ->visible(fn (GuidedAssignment $record) => $record->question->answer_type !== 'single_choice')
                        ->required(fn (GuidedAssignment $record) => $record->question->answer_type !== 'single_choice')
                        ->maxLength(2000),
                    Forms\Components\Textarea::make('notes')->label('Keterangan Tambahan'),
                    Forms\Components\Select::make('evidence_ids')->label('Evidence Terlampir')->multiple()->searchable()->preload()
                        ->options(fn (GuidedAssignment $record): array => self::evidenceOptions($record))
                        ->getSearchResultsUsing(fn (GuidedAssignment $record, string $search): array =>
                            self::evidenceOptions($record, $search))
                        ->getOptionLabelsUsing(fn (GuidedAssignment $record, array $values): array =>
                            self::evidenceOptions($record, ids: $values)),
                ])->action(function (GuidedAssignment $record, array $data): void {
                    app(GuidedAssessmentWorkflowService::class)->submitAnswer($record, auth()->user(),
                        ['value' => $record->question->answer_type === 'single_choice' ? ($data['choice'] ?? '') : ($data['value'] ?? '')], $data['context_key'], $data['notes'] ?? null, $data['evidence_ids'] ?? []);
                    Notification::make()->success()->title('Jawaban tersimpan sebagai entri baru')->send();
                }),
        ]);
    }

    private static function evidenceOptions(GuidedAssignment $record, ?string $search = null, ?array $ids = null): array
    {
        return app(GuidedEvidenceAccessService::class)
            ->selectableQuery($record, auth()->user())
            ->when($ids !== null, fn (Builder $query) => $query->whereIn('id', $ids))
            ->when(filled($search), fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'))
            ->orderBy('title')->limit($ids === null ? 50 : max(1, count($ids)))
            ->pluck('title', 'id')->all();
    }

    public static function getPages(): array { return ['index' => Pages\ListGuidedAssignments::route('/')]; }
}
