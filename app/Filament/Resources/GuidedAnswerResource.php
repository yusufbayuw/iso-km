<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedAnswerResource\Pages;
use App\Models\GuidedAnswer;
use App\Services\GuidedAssessmentWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedAnswerResource extends Resource
{
    protected static ?string $model = GuidedAnswer::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Konsolidasi Jawaban';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool { return auth()->user()?->can('guided.consolidate') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('assessment.memberships', fn ($q) => $q->where('user_id', auth()->id())->whereIn('role', ['coordinator', 'consolidator']))
            ->with(['assessment', 'question', 'respondent']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('assessment.name')->label('Asesmen'),
            Tables\Columns\TextColumn::make('question.code')->label('Pertanyaan')->searchable(),
            Tables\Columns\TextColumn::make('context_key')->label('Konteks'),
            Tables\Columns\TextColumn::make('respondent.name')->label('Responden'),
            Tables\Columns\TextColumn::make('notes')->label('Keterangan')->limit(65),
            Tables\Columns\TextColumn::make('submitted_at')->label('Dikirim')->dateTime('d/m/Y H:i'),
        ])->actions([
            Tables\Actions\Action::make('consolidate')->label('Konsolidasikan')->icon('heroicon-o-squares-plus')
                ->form([
                    Forms\Components\Select::make('method')->label('Metode')->options(['select' => 'Pilih Jawaban', 'merge' => 'Gabungkan', 'summary' => 'Kesimpulan Petugas'])->default('summary')->required(),
                    Forms\Components\Toggle::make('all_contexts')->label('Gabungkan lintas sistem/aset/konteks')
                        ->helperText('Aktifkan jika kesimpulan mewakili lebih dari satu konteks. Jawaban asli tetap disimpan.')
                        ->live()->default(false),
                    Forms\Components\Select::make('source_ids')->label('Jawaban Sumber')->multiple()->searchable()->required()
                        ->options(fn (GuidedAnswer $record, Forms\Get $get) => GuidedAnswer::query()
                            ->where('guided_assessment_id', $record->guided_assessment_id)
                            ->where('guided_question_id', $record->guided_question_id)
                            ->when(! $get('all_contexts'), fn ($q) => $q->where('context_key', $record->context_key))
                            ->with('respondent')->orderBy('id')->get()
                            ->mapWithKeys(fn (GuidedAnswer $a) => [$a->id => '#'.$a->id.' ['.$a->context_key.'] '.($a->respondent?->name ?? '-').' - '.(string) ($a->response['value'] ?? '')])->all())
                        ->default(fn (GuidedAnswer $record) => [$record->id]),
                    Forms\Components\Textarea::make('summary')->label('Hasil Konsolidasi')->required(),
                    Forms\Components\Textarea::make('reason')->label('Alasan pemilihan dan penggabungan')->required(),
                ])->action(function (GuidedAnswer $record, array $data): void {
                    app(GuidedAssessmentWorkflowService::class)->consolidate(
                        $record->assessment, $record->question, auth()->user(), $data['source_ids'],
                        $data['summary'], $data['method'], ! empty($data['all_contexts']) ? '*' : $record->context_key, $data['reason']
                    );
                    Notification::make()->success()->title('Versi konsolidasi baru dibuat')->send();
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ListGuidedAnswers::route('/')]; }
}
