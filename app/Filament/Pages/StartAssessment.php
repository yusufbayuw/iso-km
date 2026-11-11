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
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class StartAssessment extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $navigationGroup = 'Guided Assessment';
    protected static ?string $navigationLabel = 'Mulai / Lanjutkan Asesmen';
    protected static ?string $slug = 'mulai-asesmen';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.start-assessment';

    public ?int $runId = null;
    public ?int $systemId = null;
    public string $assessmentName = '';
    public string $stage = 'overview';
    public ?int $questionId = null;
    public string $contextKey = 'general';
    public string $newContextKey = 'general';
    public ?string $answer = null;
    public ?string $notes = null;
    public array $evidenceIds = [];
    public int $draftVersion = 0;
    public string $saveMessage = '';
    public ?int $contributorId = null;
    public ?int $delegateQuestionId = null;
    public string $scopeKind = 'asset';
    public string $scopeTitle = '';
    public ?string $scopeCategory = null;
    public ?string $scopeNotes = null;

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
        $data = $this->validate([
            'systemId' => ['required', 'integer'],
            'assessmentName' => ['required', 'string', 'max:255'],
        ]);
        $run = app(GuidedWizardDraftService::class)->start(
            auth()->user(), (int) $data['systemId'], $data['assessmentName']
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
        $this->newContextKey = $this->contextKey;
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
        $this->assessmentName = '';
        $this->clearDraftFields();
    }

    public function chooseQuestion(int $questionId): void
    {
        if (! $this->assignments->contains('guided_question_id', $questionId)) { abort(403); }
        $this->saveCurrentSilently();
        $this->questionId = $questionId;
        unset($this->currentAssignment, $this->evidenceChoices);
        $this->contextKey = 'general';
        $this->newContextKey = 'general';
        $this->loadDraft();
        $this->stage = 'questions';
    }

    public function openContext(): void
    {
        $this->saveCurrentSilently();
        $context = trim($this->newContextKey);
        if ($context === '' || $context === '*' || mb_strlen($context) > 120) {
            throw ValidationException::withMessages([
                'newContextKey' => 'Isi konteks yang valid (maksimal 120 karakter).',
            ]);
        }
        $this->contextKey = $context;
        $this->loadDraft();
    }

    private function clearDraftFields(): void
    {
        $this->answer = null;
        $this->notes = null;
        $this->evidenceIds = [];
        $this->draftVersion = 0;
        $this->saveMessage = '';
    }

    private function loadDraft(): void
    {
        $this->clearDraftFields();
        $assignment = $this->currentAssignment;
        if (! $assignment) { return; }

        $draft = GuidedAnswerDraft::query()->where('guided_assignment_id', $assignment->id)
            ->where('respondent_id', auth()->id())->where('context_key', $this->contextKey)->first();
        if ($draft) {
            $this->answer = $draft->response['value'] ?? null;
            $this->notes = $draft->notes;
            $this->evidenceIds = $draft->evidence_ids ?? [];
            $this->draftVersion = $draft->version;
            $this->saveMessage = 'Draft tersimpan: '.$draft->updated_at?->format('d/m/Y H:i');
            return;
        }
        $previous = GuidedAnswer::query()->where('guided_assignment_id', $assignment->id)
            ->where('respondent_id', auth()->id())->where('context_key', $this->contextKey)
            ->latest('id')->first();
        if ($previous) {
            $this->answer = $previous->response['value'] ?? null;
            $this->notes = $previous->notes;
            $this->evidenceIds = $previous->evidences()->pluck('evidences.id')->all();
            $this->saveMessage = 'Menampilkan jawaban sebelumnya. Simpan dan kirim untuk membuat revisi.';
        }
    }

    public function updatedAnswer(): void { $this->saveCurrentSilently(); }
    public function updatedNotes(): void { $this->saveCurrentSilently(); }
    public function updatedEvidenceIds(): void { $this->saveCurrentSilently(); }

    private function saveCurrentSilently(): void
    {
        if (! $this->currentAssignment || $this->run?->locked_at) { return; }
        try {
            $draft = app(GuidedWizardDraftService::class)->saveDraft(
                $this->currentAssignment, auth()->user(), $this->contextKey,
                $this->answer, $this->notes, $this->evidenceIds, $this->draftVersion
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
            $this->contextKey = $this->newContextKey = 'general';
        }
        $this->loadDraft();
    }

    public function delegate(): void
    {
        $run = $this->run;
        abort_unless($run && $run->isMember(auth()->user(), ['coordinator']), 403);
        $this->validate(['contributorId' => ['required', 'integer']]);
        app(GuidedWizardDraftService::class)->delegate($run, auth()->user(),
            User::findOrFail($this->contributorId), $this->delegateQuestionId);
        $this->contributorId = null;
        Notification::make()->success()->title('Penugasan diberikan kepada kontributor')->send();
    }

    public function saveScopeItem(): void
    {
        $run = $this->run;
        abort_unless($run && ! $run->locked_at && $run->isMember(auth()->user(), ['coordinator'])
            && auth()->user()->can('guided.assessment.manage'), 403);
        $this->validate([
            'scopeKind' => ['required', 'in:asset,activity'],
            'scopeTitle' => ['required', 'string', 'max:255'],
            'scopeCategory' => ['nullable', 'string', 'max:255'],
        ]);
        GuidedScopeItem::query()->create([
            'guided_assessment_id' => $run->id,
            'kind' => $this->scopeKind, 'title' => trim($this->scopeTitle),
            'category' => $this->scopeCategory, 'notes' => $this->scopeNotes,
            'created_by' => auth()->id(),
        ]);
        $this->scopeTitle = '';
        $this->scopeCategory = '';
        $this->scopeNotes = '';
        Notification::make()->success()->title('Data konteks tersimpan')->send();
    }

    public function showStage(string $stage): void
    {
        abort_unless(in_array($stage, ['questions', 'context', 'review', 'results'], true), 404);
        $this->saveCurrentSilently();
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
