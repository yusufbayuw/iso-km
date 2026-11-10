<?php

namespace App\Services;

use App\Models\Control;
use App\Models\Evidence;
use App\Models\GuidedAnswer;
use App\Models\GuidedApproval;
use App\Models\GuidedAssessment;
use App\Models\GuidedAssignment;
use App\Models\GuidedConsolidation;
use App\Models\GuidedDecision;
use App\Models\GuidedEvidenceAcceptance;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionTarget;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\StatementOfApplicability;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuidedAssessmentWorkflowService
{
    private function assertMutable(GuidedAssessment $run): void
    {
        if (in_array($run->status, ['locked', 'closed'], true) || $run->locked_at) {
            throw ValidationException::withMessages(['assessment' => 'Asesmen telah dikunci.']);
        }
    }

    private function allow(GuidedAssessment $run, User $user, string $permission, array $roles): void
    {
        if (! $user->can($permission) || ! $run->isMember($user, $roles)) {
            throw ValidationException::withMessages(['authorization' => 'Akses tidak diberikan untuk asesmen ini.']);
        }
    }

    public function assign(GuidedAssessment $run, GuidedQuestion $question, User $respondent, User $actor, ?string $dueAt = null): GuidedAssignment
    {
        $this->assertMutable($run);
        $this->allow($run, $actor, 'guided.assessment.manage', ['coordinator']);
        if ($question->guided_question_bank_id !== $run->guided_question_bank_id) {
            throw ValidationException::withMessages(['question' => 'Pertanyaan tidak berasal dari bank asesmen ini.']);
        }
        return DB::transaction(function () use ($run, $question, $respondent, $actor, $dueAt): GuidedAssignment {
            $run = GuidedAssessment::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertMutable($run);
            $this->allow($run, $actor, 'guided.assessment.manage', ['coordinator']);
            $run->memberships()->firstOrCreate(['user_id' => $respondent->id, 'role' => 'respondent']);
            return $run->assignments()->firstOrCreate(
                ['guided_question_id' => $question->id, 'user_id' => $respondent->id],
                ['status' => 'assigned', 'due_at' => $dueAt],
            );
        });
    }

    /** Responses are append-only; a correction points to an earlier response. */
    public function submitAnswer(GuidedAssignment $assignment, User $actor, array $response, string $contextKey = 'general', ?string $notes = null, array $evidenceIds = [], ?int $supersedesId = null): GuidedAnswer
    {
        return DB::transaction(function () use ($assignment, $actor, $response, $contextKey, $notes, $evidenceIds, $supersedesId): GuidedAnswer {
            $run = GuidedAssessment::query()->lockForUpdate()->findOrFail($assignment->guided_assessment_id);
            $assignment = GuidedAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertMutable($run);
            if ($assignment->user_id !== $actor->id || ! $actor->can('guided.answer.submit')) {
                throw ValidationException::withMessages(['authorization' => 'Hanya responden yang ditugaskan boleh mengirim jawaban.']);
            }
            if (! $run->isMember($actor, ['respondent']) || $assignment->question->guided_question_bank_id !== $run->guided_question_bank_id) {
                throw ValidationException::withMessages(['question' => 'Penugasan tidak valid.']);
            }
            $question = $assignment->question;
            $value = $response['value'] ?? null;
            if (! is_scalar($value) || trim((string) $value) === '') {
                throw ValidationException::withMessages(['response' => 'Isi jawaban yang sesuai tipe pertanyaan.']);
            }
            if ($question->answer_type === 'single_choice' && ! in_array((string) $value, array_map('strval', $question->answer_options ?? []), true)) {
                throw ValidationException::withMessages(['response' => 'Pilihan jawaban tidak tersedia dalam bank pertanyaan.']);
            }
            if ($question->answer_type === 'number' && ! is_numeric($value)) {
                throw ValidationException::withMessages(['response' => 'Jawaban harus berupa angka.']);
            }
            if ($question->answer_type === 'date' && (! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $value)
                || ! \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value))) {
                throw ValidationException::withMessages(['response' => 'Tanggal harus berformat YYYY-MM-DD.']);
            }
            $contextKey = trim($contextKey);
            if ($contextKey === '*' || $contextKey === '' || mb_strlen($contextKey) > 150 || $response === []) {
                throw ValidationException::withMessages(['response' => 'Jawaban dan konteks wajib diisi.']);
            }
            if ($supersedesId !== null && ! GuidedAnswer::query()
                ->whereKey($supersedesId)
                ->where('guided_assignment_id', $assignment->id)
                ->where('context_key', $contextKey)->exists()) {
                throw ValidationException::withMessages(['supersedes_id' => 'Revisi harus berasal dari jawaban sendiri dan konteks yang sama.']);
            }
            $evidenceIds = app(GuidedEvidenceAccessService::class)->assertSelectable($assignment, $actor, $evidenceIds);
            $answer = GuidedAnswer::query()->create([
                'guided_assessment_id' => $run->id,
                'guided_question_id' => $assignment->guided_question_id,
                'guided_assignment_id' => $assignment->id,
                'respondent_id' => $actor->id,
                'context_key' => $contextKey,
                'response' => $response,
                'notes' => $notes,
                'supersedes_id' => $supersedesId,
                'submitted_at' => now(),
            ]);
            if ($evidenceIds) { $answer->evidences()->sync($evidenceIds); }
            // New factual input invalidates prior conclusions for this context (or all contexts).
            // Keep the historical consolidation and approval records for audit.
            GuidedConsolidation::query()
                ->where('guided_assessment_id', $run->id)
                ->where('guided_question_id', $assignment->guided_question_id)
                ->whereIn('context_key', [$contextKey, '*'])
                ->where('is_current', true)->update(['is_current' => false]);
            $assignment->update(['status' => 'submitted']);
            app(GuidedQuestionBranchingService::class)->assignNext($assignment, $answer);
            return $answer;
        });
    }

    /** Preserves all source answers, including rejected or superseded ones. */
    public function consolidate(GuidedAssessment $run, GuidedQuestion $question, User $actor, array $sourceAnswerIds, string $summary, string $method = 'summary', string $contextKey = 'general', ?string $reason = null): GuidedConsolidation
    {
        $this->assertMutable($run);
        $this->allow($run, $actor, 'guided.consolidate', ['coordinator', 'consolidator']);
        if ($question->guided_question_bank_id !== $run->guided_question_bank_id || ! in_array($method, ['select', 'merge', 'summary'], true)) {
            throw ValidationException::withMessages(['question' => 'Bank pertanyaan atau metode tidak valid.']);
        }
        $sourceAnswerIds = array_values(array_unique(array_map('intval', $sourceAnswerIds)));
        $contextKey = trim($contextKey);
        if ($contextKey === '' || mb_strlen($contextKey) > 150 || trim($summary) === '' || ! count($sourceAnswerIds) || ($method === 'select' && count($sourceAnswerIds) !== 1)) {
            throw ValidationException::withMessages(['sources' => 'Pilih jawaban sumber dan isi kesimpulan yang valid.']);
        }
        return DB::transaction(function () use ($run, $question, $actor, $sourceAnswerIds, $summary, $method, $contextKey, $reason): GuidedConsolidation {
            $run = GuidedAssessment::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertMutable($run);
            $this->allow($run, $actor, 'guided.consolidate', ['coordinator', 'consolidator']);
            $sourceQuery = GuidedAnswer::query()->whereIn('id', $sourceAnswerIds)
                ->where('guided_assessment_id', $run->id)->where('guided_question_id', $question->id);
            if ($contextKey !== '*') { $sourceQuery->where('context_key', $contextKey); }
            $count = $sourceQuery->count();
            if ($count !== count($sourceAnswerIds)) {
                throw ValidationException::withMessages(['sources' => 'Semua jawaban sumber harus berasal dari pertanyaan, konteks, dan asesmen yang sama.']);
            }
            $previous = GuidedConsolidation::query()->where('guided_assessment_id', $run->id)
                ->where('guided_question_id', $question->id)->where('context_key', $contextKey)
                ->orderByDesc('version')->lockForUpdate()->first();
            if ($previous) { $previous->update(['is_current' => false]); }
            if ($contextKey === '*') {
                GuidedConsolidation::query()->where('guided_assessment_id', $run->id)
                    ->where('guided_question_id', $question->id)->where('is_current', true)
                    ->update(['is_current' => false]);
            }
            $consolidation = GuidedConsolidation::query()->create([
                'guided_assessment_id' => $run->id, 'guided_question_id' => $question->id,
                'context_key' => $contextKey, 'version' => ($previous?->version ?? 0) + 1,
                'method' => $method, 'summary' => $summary, 'reason' => $reason,
                'prepared_by' => $actor->id,
            ]);
            $consolidation->sources()->attach($sourceAnswerIds);
            return $consolidation;
        });
    }

    public function propose(GuidedConsolidation $consolidation, GuidedQuestionTarget $target, User $actor, string $status, int $riskScore, string $rationale, ?int $riskOwnerId = null, ?bool $applicable = null): GuidedDecision
    {
        $run = $consolidation->assessment;
        $this->assertMutable($run);
        $this->allow($run, $actor, 'guided.consolidate', ['coordinator', 'consolidator']);
        if (! $consolidation->is_current || $target->guided_question_id !== $consolidation->guided_question_id || $riskScore < 1 || $riskScore > 25 || trim($rationale) === '') {
            throw ValidationException::withMessages(['decision' => 'Konsolidasi, kriteria, risiko, atau justifikasi tidak valid.']);
        }
        $allowed = $target->control_id
            ? ['not_started', 'planned', 'in_progress', 'implemented', 'not_applicable']
            : ['not_assessed', 'nonconformant', 'partial', 'conformant'];
        if (! in_array($status, $allowed, true) || ($target->control_id && ($status === 'not_applicable') !== ($applicable === false))) {
            throw ValidationException::withMessages(['status' => 'Status tidak sesuai jenis target atau applicability.']);
        }
        $linkedRisks = $this->linkedRisks($target, $run);
        $highestLinkedScore = $linkedRisks->max(fn (Risk $risk) =>
            (int) ($risk->residual_score ?? $risk->inherent_score)) ?? 0;
        $riskScore = max($riskScore, $highestLinkedScore);
        if ($riskScore >= $run->high_risk_threshold) {
            if (! $riskOwnerId || ! $run->memberships()->where('user_id', $riskOwnerId)->where('role', 'risk_owner')->exists()) {
                throw ValidationException::withMessages(['risk_owner_id' => 'Risiko tinggi memerlukan pemilik risiko terdaftar pada asesmen.']);
            }
            $highRiskOwners = $linkedRisks->filter(fn (Risk $risk) =>
                (int) ($risk->residual_score ?? $risk->inherent_score) >= $run->high_risk_threshold)
                ->pluck('owner_id')->filter()->unique()->all();
            if ($highRiskOwners && ! in_array($riskOwnerId, $highRiskOwners, true)) {
                throw ValidationException::withMessages(['risk_owner_id' => 'Persetujuan harus dilakukan oleh pemilik risiko terkait.']);
            }
        }
        $competing = GuidedDecision::query()->where('guided_question_target_id', $target->id)
            ->where('guided_consolidation_id', '!=', $consolidation->id)
            ->whereHas('consolidation', fn ($q) => $q->where('guided_assessment_id', $run->id)->where('is_current', true))
            ->exists();
        if ($competing) {
            throw ValidationException::withMessages(['decision' => 'Ada penilaian aktif lain untuk kriteria ini. Konsolidasikan lintas konteks terlebih dahulu.']);
        }
        $source = $target->control_id
            ? StatementOfApplicability::query()->where('management_system_id', $run->management_system_id)->where('control_id', $target->control_id)->first()
            : RequirementAssessment::query()->where('management_system_id', $run->management_system_id)->where('requirement_id', $target->requirement_id)->first();
        $baselineStatus = $source ? ($target->control_id ? $source->implementation_status : $source->status) : null;
        return GuidedDecision::query()->firstOrCreate(
            ['guided_consolidation_id' => $consolidation->id, 'guided_question_target_id' => $target->id],
            ['proposed_status' => $status, 'applicable' => $applicable, 'rationale' => $rationale,
             'risk_score' => $riskScore, 'risk_owner_id' => $riskOwnerId,
             'baseline_status' => $baselineStatus, 'baseline_updated_at' => $source?->updated_at],
        );
    }

    private function linkedRisks(GuidedQuestionTarget $target, GuidedAssessment $run)
    {
        return Risk::query()->where('management_system_id', $run->management_system_id)
            ->where('status', '!=', 'closed')
            ->whereHas($target->control_id ? 'controls' : 'requirements', fn ($query) =>
                $target->control_id
                    ? $query->where('controls.id', $target->control_id)
                    : $query->where('requirements.id', $target->requirement_id))
            ->get();
    }

    private function assertRiskCurrent(GuidedDecision $decision, GuidedAssessment $run): void
    {
        $maximum = $this->linkedRisks($decision->target, $run)->max(fn (Risk $risk) =>
            (int) ($risk->residual_score ?? $risk->inherent_score)) ?? 0;
        if ($maximum > $decision->risk_score) {
            throw ValidationException::withMessages(['risk' => 'Risiko terhubung meningkat; buat penilaian baru dan ulangi persetujuan.']);
        }
    }

    public function acceptEvidence(GuidedDecision $decision, Evidence $evidence, User $actor, bool $accepted, string $reason): GuidedEvidenceAcceptance
    {
        $run = $decision->consolidation->assessment;
        $this->assertMutable($run);
        $this->allow($run, $actor, 'guided.evidence.review', ['reviewer', 'assessor']);
        if (! in_array($decision->status, ['submitted', 'evidence_review'], true) || $decision->approvals()->exists() || trim($reason) === '') {
            throw ValidationException::withMessages(['decision' => 'Keputusan tidak menerima pemeriksaan evidence ini.']);
        }
        if ($evidence->management_system_id !== $run->management_system_id || $evidence->owner_id === $actor->id) {
            throw ValidationException::withMessages(['evidence' => 'Evidence di luar lingkup atau tidak dapat diverifikasi sendiri.']);
        }
        if ($accepted && ($evidence->status !== 'active' || $evidence->verification_status !== 'verified' || ($evidence->expires_at && $evidence->expires_at->isPast()))) {
            throw ValidationException::withMessages(['evidence' => 'Evidence harus terverifikasi, aktif, dan belum kedaluwarsa.']);
        }
        $sourceEvidenceIds = GuidedAnswer::query()->whereIn('id', $decision->consolidation->sources()->pluck('guided_answers.id'))
            ->with('evidences:id')->get()->flatMap(fn ($a) => $a->evidences->pluck('id'))->unique();
        if (! $sourceEvidenceIds->contains($evidence->id)) {
            throw ValidationException::withMessages(['evidence' => 'Evidence belum dikaitkan pada jawaban sumber.']);
        }
        return GuidedEvidenceAcceptance::query()->updateOrCreate(
            ['guided_decision_id' => $decision->id, 'evidence_id' => $evidence->id],
            ['status' => $accepted ? 'accepted' : 'rejected', 'rationale' => $reason,
             'reviewed_by' => $actor->id, 'reviewed_at' => now()],
        );
    }

    /** Approval is always performed by an assigned reviewer, never by a submitting respondent. */
    public function approve(GuidedDecision $decision, User $actor, string $notes, bool $approved = true): GuidedDecision
    {
        return DB::transaction(function () use ($decision, $actor, $notes, $approved): GuidedDecision {
            $runId = $decision->consolidation->guided_assessment_id;
            $run = GuidedAssessment::query()->lockForUpdate()->findOrFail($runId);
            $decision = GuidedDecision::query()->lockForUpdate()->findOrFail($decision->id);
            $this->assertMutable($run);
            if (! in_array($decision->status, ['submitted', 'in_review'], true) || ! $decision->consolidation->is_current) {
                throw ValidationException::withMessages(['decision' => 'Keputusan tidak dapat disetujui pada status ini.']);
            }
            if (trim($notes) === '') { throw ValidationException::withMessages(['notes' => 'Alasan persetujuan/penolakan wajib diisi.']); }
            $this->assertRiskCurrent($decision, $run);
            $stage = $this->nextStage($decision, $run);
            if ($stage === null) { throw ValidationException::withMessages(['decision' => 'Semua tahapan telah disetujui.']); }
            $permission = match ($stage) {
                'assessor' => 'guided.assessment.review',
                'risk_owner' => 'guided.risk.approve',
                default => 'guided.management.approve',
            };
            $this->allow($run, $actor, $permission, [$stage]);
            if ($stage === 'risk_owner' && $decision->risk_owner_id !== $actor->id) {
                throw ValidationException::withMessages(['authorization' => 'Hanya pemilik risiko yang ditunjuk boleh menyetujui.']);
            }
            $sourceUserIds = $decision->consolidation->sources()->pluck('respondent_id')->all();
            if ($decision->consolidation->prepared_by === $actor->id || in_array($actor->id, $sourceUserIds, true)
                || $decision->approvals()->where('actor_id', $actor->id)->exists()) {
                throw ValidationException::withMessages(['authorization' => 'Pemisahan tugas melarang persetujuan sendiri atau persetujuan ganda.']);
            }
            if ($approved && in_array($decision->proposed_status, ['conformant', 'implemented', 'partial', 'in_progress', 'planned'], true)
                && ! $decision->acceptances()->where('status', 'accepted')->exists()) {
                throw ValidationException::withMessages(['evidence' => 'Penerapan penuh membutuhkan bukti yang diterima untuk kriteria ini.']);
            }
            $decision->approvals()->create(['stage' => $stage, 'outcome' => $approved ? 'approved' : 'rejected', 'actor_id' => $actor->id, 'notes' => $notes]);
            if (! $approved) {
                $decision->update(['status' => 'revision_required']);
            } elseif ($this->nextStage($decision, $run) === null) {
                $decision->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            } else {
                $decision->update(['status' => 'in_review']);
            }
            return $decision->refresh();
        });
    }

    public function nextStage(GuidedDecision $decision, GuidedAssessment $run): ?string
    {
        $stages = ['assessor'];
        if ($decision->risk_score >= $run->high_risk_threshold) { $stages[] = 'risk_owner'; }
        if ($decision->risk_score >= $run->critical_risk_threshold
            || ($decision->proposed_status === 'not_applicable' && $decision->target->evidence_profile === 'critical')) {
            $stages[] = 'management';
        }
        $approved = $decision->approvals()->where('outcome', 'approved')->pluck('stage')->all();
        foreach ($stages as $stage) {
            if (! in_array($stage, $approved, true)) { return $stage; }
        }
        return null;
    }

    /** Explicit publication avoids overwriting later edits to legacy SoA/assessment rows. */
    public function publish(GuidedDecision $decision, User $actor): void
    {
        DB::transaction(function () use ($decision, $actor): void {
            $runId = $decision->consolidation->guided_assessment_id;
            $run = GuidedAssessment::query()->lockForUpdate()->findOrFail($runId);
            $decision = GuidedDecision::query()->lockForUpdate()->findOrFail($decision->id);
            $this->assertMutable($run);
            $this->allow($run, $actor, 'guided.assessment.manage', ['coordinator']);
            if ($decision->status !== 'approved' || ! $decision->consolidation->is_current) {
                throw ValidationException::withMessages(['decision' => 'Hanya keputusan current yang disetujui dapat dipublikasikan.']);
            }
            $this->assertRiskCurrent($decision, $run);
            if (in_array($decision->proposed_status, ['conformant', 'implemented', 'partial', 'in_progress', 'planned'], true)
                && ! $decision->acceptances()->where('status', 'accepted')->whereHas('evidence', fn ($q) =>
                    $q->where('status', 'active')->where('verification_status', 'verified')
                        ->where(fn ($date) => $date->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                )->exists()) {
                throw ValidationException::withMessages(['evidence' => 'Bukti yang telah diterima harus tetap valid sebelum publikasi.']);
            }
            if ($decision->synced_at) { return; }
            $target = $decision->target;
            $hasCompetingOutcome = GuidedDecision::query()
                ->whereKeyNot($decision->id)
                ->whereHas('consolidation', fn ($q) => $q->where('guided_assessment_id', $run->id)->where('is_current', true))
                ->whereHas('target', function ($q) use ($target): void {
                    $target->control_id
                        ? $q->where('control_id', $target->control_id)
                        : $q->where('requirement_id', $target->requirement_id);
                })->exists();
            if ($hasCompetingOutcome) {
                throw ValidationException::withMessages(['decision' => 'Ada beberapa kriteria aktif untuk kontrol/persyaratan yang sama. Hasil akhir memerlukan konsolidasi tingkat kontrol sebelum publikasi.']);
            }
            $query = $target->control_id
                ? StatementOfApplicability::query()->where('management_system_id', $run->management_system_id)->where('control_id', $target->control_id)
                : RequirementAssessment::query()->where('management_system_id', $run->management_system_id)->where('requirement_id', $target->requirement_id);
            $existing = $query->lockForUpdate()->first();
            $status = $existing ? ($target->control_id ? $existing->implementation_status : $existing->status) : null;
            if ($status !== $decision->baseline_status || ($existing?->updated_at?->toDateTimeString() !== $decision->baseline_updated_at?->toDateTimeString())) {
                throw ValidationException::withMessages(['conflict' => 'Data sumber berubah sejak usulan dibuat. Rekonsiliasi diperlukan sebelum publikasi.']);
            }
            if ($target->control_id) {
                $record = $existing ?: new StatementOfApplicability(['management_system_id' => $run->management_system_id, 'control_id' => $target->control_id]);
                $record->fill(['implementation_status' => $decision->proposed_status, 'applicable' => $decision->proposed_status === 'not_applicable' ? false : ($decision->applicable ?? true),
                    'justification' => $decision->rationale, 'implementation_notes' => $decision->consolidation->summary, 'last_reviewed_at' => now()]);
            } else {
                $record = $existing ?: new RequirementAssessment(['management_system_id' => $run->management_system_id, 'requirement_id' => $target->requirement_id]);
                $record->fill(['status' => $decision->proposed_status, 'notes' => $decision->rationale, 'last_assessed_at' => now()]);
            }
            $record->save();
            $decision->update(['synced_at' => now()]);
        });
    }

    public function lock(GuidedAssessment $run, User $actor): void
    {
        $this->allow($run, $actor, 'guided.assessment.manage', ['coordinator']);
        DB::transaction(function () use ($run): void {
            $run = GuidedAssessment::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertMutable($run);
            $bank = $run->bank;
            $coverage = app(GuidedQuestionBankService::class)->coverage($bank);
            if ($bank->status !== 'published' || $coverage['missing_requirements'] || $coverage['missing_controls']
                || $coverage['broken_branches'] || $coverage['invalid_questions']
                || $coverage['catalog_missing_requirements'] || $coverage['catalog_missing_controls']
                || $coverage['duplicate_targets']) {
                throw ValidationException::withMessages(['coverage' => 'Bank pertanyaan belum memenuhi pemeriksaan cakupan.']);
            }
            if ($run->assignments()->where('status', '!=', 'submitted')->exists() || ! $run->assignments()->exists()) {
                throw ValidationException::withMessages(['assessment' => 'Seluruh penugasan wajib diselesaikan sebelum finalisasi.']);
            }
            $activeDecisions = GuidedDecision::query()->whereHas('consolidation', fn ($q) =>
                $q->where('guided_assessment_id', $run->id)->where('is_current', true))->with('target')->get();
            foreach ($activeDecisions as $decision) { $this->assertRiskCurrent($decision, $run); }
            $expectedTargets = GuidedQuestionTarget::query()->whereHas('question',
                fn ($q) => $q->where('guided_question_bank_id', $bank->id))->pluck('id');
            $decisions = GuidedDecision::query()->whereHas('consolidation',
                fn ($q) => $q->where('guided_assessment_id', $run->id)->where('is_current', true));
            $completed = (clone $decisions)->where('status', 'approved')->whereNotNull('synced_at')
                ->whereNotIn('proposed_status', ['not_assessed'])
                ->pluck('guided_question_target_id')->unique();
            $missingFreshEvidence = (clone $decisions)
                ->whereIn('proposed_status', ['conformant', 'implemented', 'partial', 'in_progress', 'planned'])
                ->whereDoesntHave('acceptances', fn ($q) => $q->where('status', 'accepted')
                    ->whereHas('evidence', fn ($e) => $e->where('status', 'active')->where('verification_status', 'verified')
                        ->where(fn ($d) => $d->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))))
                ->exists();
            if ($missingFreshEvidence) {
                throw ValidationException::withMessages(['evidence' => 'Ada keputusan dengan bukti tidak lagi valid untuk finalisasi.']);
            }
            if ($expectedTargets->isEmpty() || $expectedTargets->diff($completed)->isNotEmpty()
                || (clone $decisions)->where(function ($q) { $q->where('status', '!=', 'approved')->orWhereNull('synced_at'); })->exists()) {
                throw ValidationException::withMessages(['assessment' => 'Semua kriteria wajib memerlukan keputusan aktif yang telah disetujui dan dipublikasikan.']);
            }
            $run->update(['status' => 'locked', 'locked_at' => now()]);
        });
    }
}
