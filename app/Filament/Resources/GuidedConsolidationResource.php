<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuidedConsolidationResource\Pages;
use App\Models\GuidedConsolidation;
use App\Models\GuidedQuestionTarget;
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuidedConsolidationResource extends Resource
{
    protected static ?string $model = GuidedConsolidation::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Hasil Konsolidasi';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool { return config('iso-km.guided_assessment_enabled', false) && (auth()->user()?->can('guided.consolidate') ?? false); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_current', true)
            ->whereHas('assessment.memberships', fn ($q) => $q->where('user_id', auth()->id())->whereIn('role', ['coordinator', 'consolidator']))
            ->with(['assessment', 'question', 'preparer']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('assessment.name')->label('Asesmen'),
            Tables\Columns\TextColumn::make('question.code')->label('Pertanyaan'),
            Tables\Columns\TextColumn::make('context_key')->label('Konteks'),
            Tables\Columns\TextColumn::make('version')->label('Versi'),
            Tables\Columns\TextColumn::make('summary')->label('Kesimpulan')->wrap()->limit(120),
            Tables\Columns\TextColumn::make('preparer.name')->label('Petugas'),
        ])->actions([
            Tables\Actions\Action::make('propose')->label('Usulkan Penilaian')->icon('heroicon-o-clipboard-document-check')
                ->form([
                    Forms\Components\Select::make('target_id')->label('Kriteria')->options(fn (GuidedConsolidation $record) =>
                        GuidedQuestionTarget::query()->where('guided_question_id', $record->guided_question_id)->get()
                            ->mapWithKeys(fn ($t) => [$t->id => '#'.$t->id.' - '.(string) $t->criterion])->all())->searchable()->required(),
                    Forms\Components\Select::make('status')->label('Status Penilaian')->options([
                        'not_assessed' => 'Belum Dinilai', 'nonconformant' => 'Tidak Sesuai',
                        'partial' => 'Sebagian Sesuai', 'conformant' => 'Sesuai',
                        'not_started' => 'Belum Dimulai', 'planned' => 'Direncanakan',
                        'in_progress' => 'Dalam Proses', 'implemented' => 'Terimplementasi',
                        'not_applicable' => 'Tidak Berlaku (Kontrol)',
                    ])->required(),
                    Forms\Components\Toggle::make('applicable')->label('Kontrol Diperlukan')->default(true)
                        ->helperText('Untuk status Tidak Berlaku, matikan toggle dan isi justifikasi.'),
                    Forms\Components\TextInput::make('risk_score')->label('Skor Risiko (1-25)')->numeric()->minValue(1)->maxValue(25)->required(),
                    Forms\Components\Select::make('risk_owner_id')->label('Pemilik Risiko')->options(fn () => User::query()->pluck('name', 'id')->all())->searchable(),
                    Forms\Components\Textarea::make('rationale')->label('Dasar Penilaian')->required(),
                ])->action(function (GuidedConsolidation $record, array $data): void {
                    app(GuidedAssessmentWorkflowService::class)->propose(
                        $record, GuidedQuestionTarget::findOrFail($data['target_id']), auth()->user(),
                        $data['status'], (int) $data['risk_score'], $data['rationale'],
                        $data['risk_owner_id'] ?? null, (bool) ($data['applicable'] ?? true)
                    );
                    Notification::make()->success()->title('Usulan penilaian dibuat')->send();
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => Pages\ListGuidedConsolidations::route('/')]; }
}
