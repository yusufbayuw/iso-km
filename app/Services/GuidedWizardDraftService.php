<?php

namespace App\Services;

use App\Models\GuidedAnswer;
use App\Models\GuidedAnswerDraft;
use App\Models\GuidedAssessment;
use App\Models\GuidedAssignment;
use App\Models\GuidedQuestionBank;
use App\Models\ManagementSystem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuidedWizardDraftService
{
    private function assertEnabled(): void
    {
        app(GuidedFeatureGate::class)->assertEnabled();
        if (! config('iso-km.guided_wizard_enabled', false)) {
            throw ValidationException::withMessages([
                'wizard' => 'Wizard belum diaktifkan pada instalasi ini.',
            ]);
        }
    }

    public function start(User $actor, int $systemId, string $name): GuidedAssessment
    {
        $this->assertEnabled();
        if (! $actor->can('guided.assessment.manage') || ! $actor->can('guided.answer.submit')
            || trim($name) === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages([
                'assessment' => 'Nama asesmen dan izin membuat serta mengisi wajib tersedia.',
            ]);
        }
        app(GuidedSystemAccessService::class)->assertCanAccess($actor, $systemId);
        $system = ManagementSystem::query()->findOrFail($systemId);
        $bank = GuidedQuestionBank::query()->where('standard_id', $system->standard_id)
            ->where('status', 'published')
            ->orderByDesc('version')->orderByDesc('id')->first();
        if (! $bank) {
            throw ValidationException::withMessages([
                'bank' => 'Belum ada bank pertanyaan terbit untuk standar ini. Administrator harus memeriksa dan menerbitkan bank terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($actor, $system, $bank, $name): GuidedAssessment {
            $run = GuidedAssessment::query()->create([
                'management_system_id' => $system->id,
                'guided_question_bank_id' => $bank->id,
                'name' => trim($name),
                'status' => 'draft',
                'created_by' => $actor->id,
                'started_at' => now(),
            ]);
            $run->memberships()->firstOrCreate(['user_id' => $actor->id, 'role' => 'respondent']);
            $count = app(GuidedQuestionBranchingService::class)->assignRoots($run, $actor, $actor);
            if ($count < 1) {
                throw ValidationException::withMessages(['questions' => 'Bank belum memiliki pertanyaan awal.']);
            }
            return $run;
        });
    }

    private function assignmentForActor(GuidedAssignment $assignment, User $actor): GuidedAssignment
    {
        $this->assertEnabled();
        $assignment = GuidedAssignment::query()->with(['assessment', 'question'])
            ->findOrFail($assignment->id);
        $run = $assignment->assessment;
        if ($assignment->user_id !== $actor->id
            || ! $run->isMember($actor, ['respondent'])
            || ! $actor->can('guided.answer.submit')
            || $run->locked_at || in_array($run->status, ['locked', 'closed'], true)
            || $assignment->status === 'skipped'
            || $assignment->question->guided_question_bank_id !== $run->guided_question_bank_id) {
            throw ValidationException::withMessages(['assignment' =>
                'Penugasan tidak aktif atau bukan kewenangan pengguna.']);
        }
        return $assignment;
    }

    private function validateContext(string $context): string
    {
        $context = trim($context);
        if ($context === '' || $context === '*' || mb_strlen($context) > 120) {
            throw ValidationException::withMessages(['context_key' =>
                'Nama konteks wajib diisi (maksimum 120 karakter).']);
        }
        return $context;
    }

    public function saveDraft(
        GuidedAssignment $assignment,
        User $actor,
        string $contextKey,
        ?string $value,
        ?string $notes,
        array $evidenceIds = [],
        int $expectedVersion = 0
    ): GuidedAnswerDraft {
        $assignment = $this->assignmentForActor($assignment, $actor);
        $contextKey = $this->validateContext($contextKey);
        if ($expectedVersion < 0 || ($value !== null && mb_strlen($value) > 10000)
            || ($notes !== null && mb_strlen($notes) > 100000)) {
            throw ValidationException::withMessages(['draft' => 'Isi draft terlalu panjang atau versi tidak valid.']);
        }
        $evidenceIds = app(GuidedEvidenceAccessService::class)
            ->assertSelectable($assignment, $actor, $evidenceIds);

        return DB::transaction(function () use ($assignment, $actor, $contextKey,
            $value, $notes, $evidenceIds, $expectedVersion): GuidedAnswerDraft {
            $draft = GuidedAnswerDraft::query()->where('guided_assignment_id', $assignment->id)
                ->where('respondent_id', $actor->id)->where('context_key', $contextKey)
                ->lockForUpdate()->first();
            if ($draft && $draft->version !== $expectedVersion) {
                throw ValidationException::withMessages(['draft' =>
                    'Draft telah diperbarui dari sesi lain. Muat ulang sebelum menyimpan agar perubahan tidak tertimpa.']);
            }
            if (! $draft && $expectedVersion !== 0) {
                throw ValidationException::withMessages(['draft' =>
                    'Versi draft tidak sesuai. Muat ulang jawaban.']);
            }
            if (! $draft) {
                return GuidedAnswerDraft::query()->create([
                    'guided_assessment_id' => $assignment->guided_assessment_id,
                    'guided_question_id' => $assignment->guided_question_id,
                    'guided_assignment_id' => $assignment->id,
                    'respondent_id' => $actor->id,
                    'context_key' => $contextKey,
                    'response' => ['value' => $value],
                    'notes' => $notes,
                    'evidence_ids' => $evidenceIds,
                    'version' => 1,
                ]);
            }
            $draft->update([
                'response' => ['value' => $value],
                'notes' => $notes,
                'evidence_ids' => $evidenceIds,
                'version' => $draft->version + 1,
            ]);
            return $draft->refresh();
        });
    }

    public function submit(GuidedAssignment $assignment, User $actor, string $contextKey): GuidedAnswer
    {
        $assignment = $this->assignmentForActor($assignment, $actor);
        $contextKey = $this->validateContext($contextKey);
        $draft = GuidedAnswerDraft::query()->where('guided_assignment_id', $assignment->id)
            ->where('respondent_id', $actor->id)->where('context_key', $contextKey)->first();
        $value = $draft?->response['value'] ?? null;
        if (! is_scalar($value) || trim((string) $value) === '') {
            throw ValidationException::withMessages(['answer' => 'Jawaban belum diisi. Simpan draft terlebih dahulu.']);
        }
        $previous = GuidedAnswer::query()->where('guided_assignment_id', $assignment->id)
            ->where('respondent_id', $actor->id)->where('context_key', $contextKey)
            ->orderByDesc('id')->value('id');

        return app(GuidedAssessmentWorkflowService::class)->submitAnswer(
            $assignment, $actor, ['value' => $value], $contextKey,
            $draft->notes, $draft->evidence_ids ?? [], $previous
        );
    }

    public function delegate(GuidedAssessment $run, User $actor, User $respondent,
        ?int $questionId = null): int
    {
        $this->assertEnabled();
        if ($run->locked_at || ! $run->isMember($actor, ['coordinator'])
            || ! $actor->can('guided.assessment.manage')
            || ! $respondent->can('guided.answer.submit')) {
            throw ValidationException::withMessages(['authorization' =>
                'Koordinator harus berwenang dan penerima tugas harus memiliki izin menjawab.']);
        }
        return DB::transaction(function () use ($run, $actor, $respondent, $questionId): int {
            $run->memberships()->firstOrCreate([
                'user_id' => $respondent->id, 'role' => 'respondent',
            ]);
            if ($questionId) {
                $question = $run->bank->questions()->whereKey($questionId)->firstOrFail();
                app(GuidedAssessmentWorkflowService::class)->assign($run, $question, $respondent, $actor);
                return 1;
            }
            return app(GuidedQuestionBranchingService::class)->assignRoots($run, $respondent, $actor);
        });
    }
}
