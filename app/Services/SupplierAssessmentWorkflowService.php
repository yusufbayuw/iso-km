<?php

namespace App\Services;

use App\Models\SupplierAssessment;
use App\Models\User;
use App\Notifications\IsoWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SupplierAssessmentWorkflowService
{
    public function request(SupplierAssessment $assessment, User $actor): SupplierAssessment
    {
        $this->authorize($actor, 'supplier.assess');
        $this->assertStatus($assessment, ['planned']);

        $assessment->update([
            'status' => 'requested',
            'requested_by' => $actor->id,
            'requested_at' => now(),
        ]);

        $assessment->supplier?->owner?->notify(new IsoWorkflowNotification(
            'supplier_assessment_requested',
            'Assessment supplier diminta',
            "Assessment {$assessment->supplier?->code} menunggu pelaksanaan.",
        ));

        return $assessment->refresh();
    }

    public function start(SupplierAssessment $assessment, User $actor): SupplierAssessment
    {
        $this->authorize($actor, 'supplier.assess');
        $this->assertStatus($assessment, ['planned', 'requested']);

        $assessment->update([
            'status' => 'in_progress',
            'assessed_by' => $actor->id,
            'assessed_at' => $assessment->assessed_at ?: today(),
        ]);

        return $assessment->refresh();
    }

    public function submit(SupplierAssessment $assessment, User $actor, string $summary, ?int $evidenceId = null): SupplierAssessment
    {
        $this->authorize($actor, 'supplier.assess');
        $this->assertStatus($assessment, ['requested', 'in_progress']);

        if (trim($summary) === '') {
            throw ValidationException::withMessages(['summary' => 'Ringkasan assessment wajib diisi sebelum submission.']);
        }

        $assessment->update([
            'status' => 'submitted',
            'assessed_by' => $assessment->assessed_by ?: $actor->id,
            'assessed_at' => $assessment->assessed_at ?: today(),
            'submitted_at' => now(),
            'summary' => $summary,
            'evidence_id' => $evidenceId ?: $assessment->evidence_id,
        ]);

        return $assessment->refresh();
    }

    public function beginReview(SupplierAssessment $assessment, User $actor, ?string $notes = null): SupplierAssessment
    {
        $this->authorize($actor, 'supplier.review');
        $this->assertStatus($assessment, ['submitted']);

        $assessment->update([
            'status' => 'under_review',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $assessment->refresh();
    }

    public function decide(
        SupplierAssessment $assessment,
        User $actor,
        string $decision,
        string $riskRating,
        ?float $score = null,
        ?string $requiredActions = null,
        ?string $nextReviewAt = null,
    ): SupplierAssessment {
        $this->authorize($actor, 'supplier.decide');
        $this->assertStatus($assessment, ['under_review']);

        if (! in_array($decision, ['accepted', 'conditional', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Keputusan supplier tidak valid.']);
        }

        if (! in_array($riskRating, ['low', 'medium', 'high', 'critical'], true)) {
            throw ValidationException::withMessages(['risk_rating' => 'Risk rating supplier tidak valid.']);
        }

        if ($decision === 'conditional' && ! trim((string) $requiredActions)) {
            throw ValidationException::withMessages(['required_actions' => 'Tindak lanjut wajib ditentukan untuk keputusan conditional.']);
        }

        $assessment->update([
            'status' => $decision,
            'risk_rating' => $riskRating,
            'score' => $score,
            'required_actions' => $requiredActions,
            'next_review_at' => $nextReviewAt ?: $assessment->next_review_at,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        $assessment->supplier?->owner?->notify(new IsoWorkflowNotification(
            'supplier_assessment_decision',
            'Keputusan assessment supplier',
            "{$assessment->supplier?->code} diputuskan: {$decision} ({$riskRating}).",
        ));

        return $assessment->refresh();
    }

    public function verifyTreatment(SupplierAssessment $assessment, User $actor, string $notes): SupplierAssessment
    {
        $this->authorize($actor, 'supplier.verify_treatment');
        $this->assertStatus($assessment, ['conditional']);

        if (trim($notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Catatan verifikasi treatment wajib diisi.']);
        }

        $assessment->update([
            'status' => 'accepted',
            'treatment_verified_by' => $actor->id,
            'treatment_verified_at' => now(),
            'treatment_verification_notes' => $notes,
        ]);

        return $assessment->refresh();
    }

    public function close(SupplierAssessment $assessment, User $actor): SupplierAssessment
    {
        $this->authorize($actor, 'supplier.decide');
        $this->assertStatus($assessment, ['accepted', 'rejected']);

        $assessment->update(['status' => 'closed']);

        return $assessment->refresh();
    }

    private function assertStatus(SupplierAssessment $assessment, array $allowed): void
    {
        if (! in_array($assessment->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Transisi supplier assessment tidak valid dari status {$assessment->status}."]);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Permission {$permission} diperlukan.");
        }
    }
}
