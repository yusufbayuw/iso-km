<?php

namespace App\Filament\Pages;

use App\Filament\Resources\GuidedAnswerResource;
use App\Filament\Resources\GuidedConsolidationResource;
use App\Filament\Resources\GuidedDecisionResource;
use App\Filament\Resources\GuidedScopeItemResource;
use App\Filament\Resources\RiskResource;
use App\Models\GuidedAnswer;
use App\Models\GuidedAnswerDraft;
use App\Models\GuidedAssessment;
use App\Models\GuidedAssignment;
use App\Models\GuidedExportTemplate;
use App\Models\GuidedScopeItem;
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use App\Services\GuidedEvidenceAccessService;
use App\Services\GuidedSystemAccessService;
use App\Services\GuidedWizardCompletenessService;
use App\Services\GuidedWizardDraftService;
use App\Services\GuidedWorkbookExportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class StartAssessment extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Mulai / Lanjutkan Asesmen';
    protected static ?string $title = 'Mulai / Lanjutkan Asesmen';
    protected static ?string $slug = 'mulai-asesmen';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.start-assessment';

    public ?int $runId = null;
    public string $stage = 'overview';
    public ?int $questionId = null;
    public string $contextKey = 'general';
    public int $draftVersion = 0;
    public string $saveMessage = '';

    // Native Filament Forms own all input state. Do not mix raw HTML form inputs
    // or independent Livewire properties into the same validation flow.
    public ?array $startData = [];
    public ?array $runData = [];
    public ?array $questionPickerData = [];
    public ?array $contextData = [];
    public ?array $answerData = [];
    public ?array $scopeData = [];
    public ?array $delegateData = [];

    protected function getForms(): array
    {
        return [
            'startForm',
            'runSelectorForm',
            'questionPickerForm',
            'contextForm',
            'answerForm',
            'scopeForm',
            'delegateForm',
        ];
    }

    public function startForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('system_id')
                ->label('Sistem manajemen yang dinilai')
                ->options(fn (): array => $this->systems)
                ->placeholder('Pilih sistem manajemen')
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->helperText('Hanya sistem dalam lingkup kewenangan Anda.'),
            Forms\Components\TextInput::make('name')
                ->label('Nama asesmen')
                ->placeholder('Contoh: Asesmen Keamanan Informasi Semester 1')
                ->required()
                ->maxLength(255),
        ])->statePath('startData')->columns(1);
    }

    public function runSelectorForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('run_id')
                ->label('Asesmen yang dapat Anda akses')
                ->options(fn (): array => $this->runs->mapWithKeys(
                    fn ($run) => [$run->id => $run->name.' — '.($run->system?->name ?? '-')]
                )->all())
                ->placeholder('Pilih asesmen')
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->afterStateUpdated(function ($state): void {
                    if (filled($state) && (int) $state !== $this->runId) {
                        $this->chooseRun((int) $state);
                    }
                }),
        ])->statePath('runData');
    }

    public function questionPickerForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('question_id')
                ->label('Pilih pertanyaan')
                ->options(fn (): array => $this->assignments->mapWithKeys(
                    fn (GuidedAssignment $assignment) => [
                        $assignment->guided_question_id => $assignment->question->code
                            .' — '.$assignment->question->prompt
                            .' ['.($assignment->status === 'submitted' ? 'Terkirim' : 'Belum selesai').']',
                    ]
                )->all())
                ->placeholder('Cari berdasarkan kode atau isi pertanyaan')
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->afterStateUpdated(function ($state): void {
                    if (filled($state) && (int) $state !== $this->questionId) {
                        $this->chooseQuestion((int) $state);
                    }
                }),
        ])->statePath('questionPickerData');
    }

    public function contextForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('context_key')
                ->label('Konteks / sistem / aset')
                ->required()
                ->maxLength(120)
                ->default('general')
                ->helperText('Gunakan konteks berbeda untuk mencatat jawaban per aset atau sistem.'),
        ])->statePath('contextData');
    }

    private function answerFields(): array
    {
        $question = $this->currentAssignment?->question;
        if (! $question) { return []; }

        $field = match ($question->answer_type) {
            'single_choice' => Forms\Components\Select::make('value')
                ->options(collect($question->answer_options ?? [])->mapWithKeys(
                    fn ($choice) => [(string) $choice => (string) $choice]
                )->all())
                ->placeholder('Pilih kondisi yang sesuai')
                ->native(false),
            'number' => Forms\Components\TextInput::make('value')->numeric(),
            'date' => Forms\Components\DatePicker::make('value')->native(false),
            default => Forms\Components\Textarea::make('value')->rows(4)->maxLength(10000),
        };
        $field->label('Jawaban')
            ->helperText('Isi keadaan yang sebenarnya. Kirim jawaban setelah siap untuk diperiksa.')
            ->live(onBlur: true)
            ->afterStateUpdated(fn () => $this->saveCurrentSilently());

        return [
            $field,
            Forms\Components\Textarea::make('notes')
                ->label('Keterangan tambahan')
                ->rows(3)
                ->maxLength(100000)
                ->placeholder('Jelaskan kondisi, penanggung jawab, hambatan, dan tindak lanjut...')
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->saveCurrentSilently()),
            Forms\Components\Select::make('evidence_ids')
                ->label('Bukti pendukung yang tersedia')
                ->helperText('Jika belum ada bukti, tetap catat kondisi sebenarnya. Pemeriksa dapat meminta bukti tambahan.')
                ->options(fn (): array => $this->evidenceChoices)
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->saveCurrentSilently()),
        ];
    }

    public function answerForm(Form $form): Form
    {
        return $form->schema(fn (): array => $this->answerFields())
            ->statePath('answerData')->columns(1);
    }

    public function scopeForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('kind')
                ->label('Jenis data')
                ->options(['asset' => 'Aset informasi', 'activity' => 'Aktivitas informasi'])
                ->native(false)->required()->default('asset'),
            Forms\Components\TextInput::make('title')
                ->label('Nama aset / aktivitas')->required()->maxLength(255),
            Forms\Components\TextInput::make('category')
                ->label('Kategori')->maxLength(255),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')->rows(2)->columnSpanFull(),
        ])->statePath('scopeData')->columns(2);
    }

    public function delegateForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('contributor_id')
                ->label('Kontributor')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->permission('guided.answer.submit')
                    ->where('name', 'like', '%'.$search.'%')
                    ->orderBy('name')->limit(30)->pluck('name', 'id')->all())
                ->getOptionLabelUsing(fn ($value): ?string =>
                    User::query()->whereKey($value)->value('name'))
                ->helperText('Hanya pengguna dengan izin menjawab yang dapat ditugaskan.'),
            Forms\Components\Select::make('question_id')
                ->label('Cakupan pertanyaan')
                ->options(fn (): array => $this->run
                    ? $this->run->bank->questions()->whereHas('targets')
                        ->orderBy('sort_order')->pluck('code', 'id')->all()
                    : [])
                ->placeholder('Seluruh pertanyaan awal')
                ->searchable()->preload()->native(false),
        ])->statePath('delegateData')->columns(2);
    }

    public static function canAccess(): bool
    {
        return (bool) config('iso-km.guided_assessment_enabled', false)
            && (bool) config('iso-km.guided_wizard_enabled', false)
            && (auth()->user()?->can('guided.assessment.view') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->startForm->fill();
        $this->runSelectorForm->fill();
        $this->questionPickerForm->fill();
        $this->contextForm->fill(['context_key' => 'general']);
        $this->answerForm->fill();
        $this->scopeForm->fill(['kind' => 'asset']);
        $this->delegateForm->fill();

        $requested = (int) request()->query('run', 0);
        $run = $requested ? $this->visibleRun($requested)
            : GuidedAssessment::query()->whereHas('memberships', fn ($q) =>
                $q->where('user_id', auth()->id()))->latest('id')->first();
        if ($requested && ! $run) { abort(403); }
        if ($run) { $this->openRun($run); }
    }

    private function visibleRun(int $id): ?GuidedAssessment
    {
        return GuidedAssessment::query()->whereKey($id)
            ->whereHas('memberships', fn ($q) => $q->where('user_id', auth()->id()))
            ->with(['bank', 'system'])->first();
    }

    #[Computed]
    public function run(): ?GuidedAssessment
    {
        return $this->runId ? $this->visibleRun($this->runId) : null;
    }

    #[Computed]
    public function runs()
    {
        return GuidedAssessment::query()->whereHas('memberships', fn ($q) =>
            $q->where('user_id', auth()->id()))->with('system')->latest('id')->limit(100)->get();
    }

    #[Computed]
    public function systems(): array
    {
        if (! ($user = auth()->user()) || ! $user->can('guided.assessment.manage')
            || ! $user->can('guided.answer.submit')) { return []; }

        return app(GuidedSystemAccessService::class)->accessibleSystems($user)
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function assignments()
    {
        $run = $this->run;
        return $run ? $run->assignments()->where('user_id', auth()->id())
            ->where('status', '!=', 'skipped')->with(['question.targets'])
            ->get()->sortBy(fn ($a) => $a->question->sort_order)->values() : collect();
    }

    #[Computed]
    public function currentAssignment(): ?GuidedAssignment
    {
        return $this->assignments->firstWhere('guided_question_id', $this->questionId);
    }

    #[Computed]
    public function evidenceChoices(): array
    {
        $assignment = $this->currentAssignment;
        return $assignment ? app(GuidedEvidenceAccessService::class)
            ->selectableQuery($assignment, auth()->user())->orderBy('title')->limit(100)
            ->pluck('title', 'id')->all() : [];
    }

    #[Computed]
    public function completeness(): ?array
    {
        return $this->run ? app(GuidedWizardCompletenessService::class)->report($this->run) : null;
    }

    public function start(): void
    {
        $data = $this->startForm->getState();
        $run = app(GuidedWizardDraftService::class)->start(
            auth()->user(), (int) $data['system_id'], $data['name']
        );
        $this->openRun($run);
        $this->stage = 'questions';
        Notification::make()->success()->title('Asesmen baru dibuat. Jawaban dapat disimpan bertahap.')->send();
    }

    private function openRun(GuidedAssessment $run): void
    {
        $this->runId = $run->id;
        $this->stage = $run->locked_at ? 'results' : 'questions';
        unset($this->run, $this->assignments, $this->currentAssignment, $this->completeness);
        $recent = GuidedAnswerDraft::query()->where('guided_assessment_id', $run->id)
            ->where('respondent_id', auth()->id())->latest('updated_at')->first();
        $assignments = $this->assignments;
        $preferred = $recent && $assignments->contains('guided_question_id', $recent->guided_question_id)
            ? $recent->guided_question_id
            : ($assignments->first(fn ($a) => $a->status !== 'submitted')?->guided_question_id
                ?? $assignments->first()?->guided_question_id);
        $this->questionId = $preferred;
        $this->contextKey = $recent && $recent->guided_question_id === $preferred
            ? $recent->context_key : 'general';

        $this->runSelectorForm->fill(['run_id' => $run->id]);
        $this->questionPickerForm->fill(['question_id' => $preferred]);
        $this->contextForm->fill(['context_key' => $this->contextKey]);
        $this->loadDraft();
    }

    public function chooseRun(int $id): void
    {
        $this->saveCurrentSilently();
        $run = $this->visibleRun($id);
        abort_unless($run, 403);
        $this->openRun($run);
    }

    public function newAssessment(): void
    {
        abort_unless(count($this->systems) > 0, 403);
        $this->saveCurrentSilently();
        $this->runId = null;
        $this->stage = 'overview';
        $this->questionId = null;
        $this->contextKey = 'general';
        unset($this->run, $this->assignments, $this->currentAssignment, $this->completeness);
        $this->startForm->fill();
        $this->runSelectorForm->fill();
        $this->questionPickerForm->fill();
        $this->contextForm->fill(['context_key' => 'general']);
        $this->answerForm->fill();
    }

    public function chooseQuestion(int $questionId): void
    {
        if (! $this->assignments->contains('guided_question_id', $questionId)) { abort(403); }
        $this->saveCurrentSilently();
        $this->questionId = $questionId;
        unset($this->currentAssignment, $this->evidenceChoices);
        $this->questionPickerForm->fill(['question_id' => $questionId]);
        $this->contextKey = 'general';
        $this->contextForm->fill(['context_key' => 'general']);
        $this->loadDraft();
        $this->stage = 'questions';
    }

    public function relativeQuestion(int $offset): void
    {
        abort_unless(in_array($offset, [-1, 1], true), 400);
        $ids = $this->assignments->pluck('guided_question_id')->values();
        $position = $ids->search($this->questionId);
        if ($position === false) { return; }
        $next = $ids->get($position + $offset);
        if ($next) { $this->chooseQuestion((int) $next); }
    }

    public function openContext(): void
    {
        $this->saveCurrentSilently();
        $data = $this->contextForm->getState();
        $context = trim($data['context_key']);
        if ($context === '*' || $context === '') {
            throw ValidationException::withMessages([
                'contextData.context_key' => 'Isi nama konteks selain *.',
            ]);
        }
        $this->contextKey = $context;
        $this->loadDraft();
    }

    private function loadDraft(): void
    {
        $value = null;
        $notes = null;
        $evidenceIds = [];
        $this->draftVersion = 0;
        $this->saveMessage = '';
        $assignment = $this->currentAssignment;

        if ($assignment) {
            $draft = GuidedAnswerDraft::query()->where('guided_assignment_id', $assignment->id)
                ->where('respondent_id', auth()->id())->where('context_key', $this->contextKey)->first();
            if ($draft) {
                $value = $draft->response['value'] ?? null;
                $notes = $draft->notes;
                $evidenceIds = $draft->evidence_ids ?? [];
                $this->draftVersion = $draft->version;
                $this->saveMessage = 'Draft tersimpan: '.$draft->updated_at?->format('d/m/Y H:i');
            } else {
                $previous = GuidedAnswer::query()->where('guided_assignment_id', $assignment->id)
                    ->where('respondent_id', auth()->id())->where('context_key', $this->contextKey)
                    ->latest('id')->first();
                if ($previous) {
                    $value = $previous->response['value'] ?? null;
                    $notes = $previous->notes;
                    $evidenceIds = $previous->evidences()->pluck('evidences.id')->all();
                    $this->saveMessage = 'Menampilkan jawaban sebelumnya. Simpan dan kirim untuk merevisi.';
                }
            }
        }

        $this->answerForm->fill([
            'value' => $value,
            'notes' => $notes,
            'evidence_ids' => $evidenceIds,
        ]);
    }

    private function saveCurrentSilently(): void
    {
        if (! $this->currentAssignment || $this->run?->locked_at) { return; }
        try {
            $data = $this->answerForm->getState();
            $draft = app(GuidedWizardDraftService::class)->saveDraft(
                $this->currentAssignment, auth()->user(), $this->contextKey,
                isset($data['value']) ? (string) $data['value'] : null,
                $data['notes'] ?? null,
                $data['evidence_ids'] ?? [],
                $this->draftVersion
            );
            $this->draftVersion = $draft->version;
            $this->saveMessage = 'Tersimpan: '.now()->format('H:i:s');
        } catch (ValidationException $error) {
            $this->saveMessage = 'Belum tersimpan; periksa kesalahan pada isian.';
            $this->addError('draft', collect($error->errors())->flatten()->first() ?? $error->getMessage());
        }
    }

    public function saveDraft(): void
    {
        if (! $this->currentAssignment) { return; }
        $this->resetErrorBag();
        $this->saveCurrentSilently();
        if (! $this->getErrorBag()->isNotEmpty()) {
            Notification::make()->success()->title('Draft tersimpan')->send();
        }
    }

    public function submitAnswer(): void
    {
        $assignment = $this->currentAssignment;
        if (! $assignment) { return; }
        $this->saveDraft();
        if ($this->getErrorBag()->isNotEmpty()) { return; }
        app(GuidedWizardDraftService::class)->submit($assignment, auth()->user(), $this->contextKey);
        Notification::make()->success()->title('Jawaban dikirim. Histori sebelumnya tetap tersimpan.')->send();
        unset($this->assignments, $this->currentAssignment, $this->completeness);
        $next = $this->assignments->first(fn ($a) => $a->status !== 'submitted');
        if ($next && $next->guided_question_id !== $this->questionId) {
            $this->questionId = $next->guided_question_id;
            $this->contextKey = 'general';
        }
        $this->questionPickerForm->fill(['question_id' => $this->questionId]);
        $this->contextForm->fill(['context_key' => $this->contextKey]);
        $this->loadDraft();
    }

    public function delegate(): void
    {
        $run = $this->run;
        abort_unless($run && $run->isMember(auth()->user(), ['coordinator']), 403);
        $data = $this->delegateForm->getState();
        app(GuidedWizardDraftService::class)->delegate(
            $run, auth()->user(), User::findOrFail((int) $data['contributor_id']),
            filled($data['question_id'] ?? null) ? (int) $data['question_id'] : null
        );
        $this->delegateForm->fill();
        Notification::make()->success()->title('Penugasan diberikan kepada kontributor')->send();
    }

    public function saveScopeItem(): void
    {
        $run = $this->run;
        abort_unless($run && ! $run->locked_at && $run->isMember(auth()->user(), ['coordinator'])
            && auth()->user()->can('guided.assessment.manage'), 403);
        $data = $this->scopeForm->getState();
        GuidedScopeItem::query()->create([
            'guided_assessment_id' => $run->id,
            'kind' => $data['kind'],
            'title' => trim($data['title']),
            'category' => $data['category'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $this->scopeForm->fill(['kind' => 'asset']);
        Notification::make()->success()->title('Data konteks tersimpan')->send();
    }

    public function showStage(string $stage): void
    {
        abort_unless(in_array($stage, ['questions', 'context', 'review', 'results'], true), 404);
        if ($this->stage === 'questions') { $this->saveCurrentSilently(); }
        $this->stage = $stage;
    }

    public function finalize(): void
    {
        $run = $this->run;
        abort_unless($run, 403);
        app(GuidedAssessmentWorkflowService::class)->lock($run, auth()->user());
        $this->stage = 'results';
        unset($this->run, $this->completeness);
        Notification::make()->success()->title('Asesmen dikunci dan siap diekspor sebagai FINAL')->send();
    }

    public function finalizeAction(): Action
    {
        return Action::make('finalize')
            ->label('Tetapkan Final')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Finalisasi asesmen')
            ->modalDescription('Asesmen yang sudah final tidak dapat disunting. Seluruh penilaian harus disetujui dan dipublikasikan.')
            ->modalSubmitActionLabel('Kunci Asesmen')
            ->action(fn () => $this->finalize());
    }

    public function downloadWorkbook()
    {
        $run = $this->run;
        abort_unless($run, 403);
        $template = GuidedExportTemplate::query()->where('is_active', true)
            ->where('name', 'GAP Analysis ISO/IEC 27001:2022 - Template Standar')
            ->whereNull('management_system_id')->first();
        if (! $template) {
            throw ValidationException::withMessages(['template' =>
                'Template bawaan belum disiapkan. Jalankan seeder katalog ISO 27001 terlebih dahulu.']);
        }
        $final = $run->locked_at !== null;
        $path = app(GuidedWorkbookExportService::class)->export(
            $run, $template, auth()->user(), $final
        );
        return response()->download(Storage::disk('local')->path($path),
            'ISO-KM-Asesmen-'.$run->id.($final ? '-FINAL' : '-DRAFT').'.xlsx');
    }

    public function getReviewLinks(): array
    {
        return [
            'answers' => GuidedAnswerResource::getUrl('index'),
            'consolidations' => GuidedConsolidationResource::getUrl('index'),
            'decisions' => GuidedDecisionResource::getUrl('index'),
            'inventory' => GuidedScopeItemResource::getUrl('index'),
            'risks' => RiskResource::getUrl('index'),
        ];
    }
}
