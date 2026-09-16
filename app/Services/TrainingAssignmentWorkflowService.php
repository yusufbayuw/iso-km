<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Notifications\IsoWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class TrainingAssignmentWorkflowService
{
    public function start(TrainingAssignment $assignment, User $actor): TrainingAssignment
    {
        $this->authorize($actor, 'training.complete');
        $this->assertOwner($assignment, $actor);
        $this->assertStatus($assignment, ['assigned', 'failed', 'rejected']);

        $assignment->update([
            'status' => 'in_progress',
            'started_at' => $assignment->started_at ?: now(),
            'submitted_at' => null,
            'completed_at' => null,
            'result' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'verification_notes' => null,
        ]);

        return $assignment->refresh();
    }

    public function submit(TrainingAssignment $assignment, User $actor, ?Evidence $evidence = null, ?string $notes = null): TrainingAssignment
    {
        $this->authorize($actor, 'training.complete');
        $this->assertOwner($assignment, $actor);
        $this->assertStatus($assignment, ['assigned', 'in_progress', 'failed', 'rejected']);

        $assignment->update([
            'status' => 'submitted',
            'started_at' => $assignment->started_at ?: now(),
            'submitted_at' => now(),
            'completed_at' => null,
            'result' => 'pending',
            'evidence_id' => $evidence?->id ?: $assignment->evidence_id,
            'notes' => $notes ?: $assignment->notes,
            'verified_by' => null,
            'verified_at' => null,
            'verification_notes' => null,
        ]);

        $assignment->program?->owner?->notify(new IsoWorkflowNotification(
            'training_submission',
            'Training menunggu verifikasi',
            "{$assignment->user?->name} telah submit {$assignment->program?->code} ({$assignment->cycle_key}).",
        ));

        return $assignment->refresh();
    }

    public function verify(TrainingAssignment $assignment, User $actor, string $result, ?float $score = null, ?string $notes = null): TrainingAssignment
    {
        $this->authorize($actor, 'training.verify');
        $this->assertStatus($assignment, ['submitted']);

        if (! in_array($result, ['pass', 'attended', 'fail', 'exempt'], true)) {
            throw ValidationException::withMessages(['result' => 'Hasil verifikasi training tidak valid.']);
        }

        $successful = in_array($result, ['pass', 'attended'], true);
        $status = match ($result) {
            'pass', 'attended' => 'completed',
            'exempt' => 'exempt',
            default => 'failed',
        };

        $validUntil = null;
        if ($successful && $assignment->program?->validity_months) {
            $validUntil = today()->addMonths($assignment->program->validity_months);
        }

        $assignment->update([
            'status' => $status,
            'result' => $result,
            'score' => $score,
            'completed_at' => $successful ? now() : null,
            'valid_until' => $validUntil,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);

        if ($assignment->evidence) {
            $assignment->evidence->update([
                'verification_status' => $successful ? 'verified' : 'rejected',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_notes' => $notes,
            ]);
        }

        $assignment->user?->notify(new IsoWorkflowNotification(
            'training_result',
            'Hasil training diverifikasi',
            "{$assignment->program?->code} ({$assignment->cycle_key}) diverifikasi dengan hasil {$result}.",
        ));

        return $assignment->refresh();
    }

    public function reject(TrainingAssignment $assignment, User $actor, string $notes): TrainingAssignment
    {
        $this->authorize($actor, 'training.verify');
        $this->assertStatus($assignment, ['submitted']);

        $assignment->update([
            'status' => 'rejected',
            'result' => 'pending',
            'submitted_at' => null,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);

        if ($assignment->evidence) {
            $assignment->evidence->update([
                'verification_status' => 'rejected',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_notes' => $notes,
            ]);
        }

        $assignment->user?->notify(new IsoWorkflowNotification(
            'training_rejected',
            'Submission training dikembalikan',
            "{$assignment->program?->code} dikembalikan. Catatan: {$notes}",
        ));

        return $assignment->refresh();
    }

    private function assertOwner(TrainingAssignment $assignment, User $actor): void
    {
        if ($assignment->user_id !== $actor->id) {
            throw new AuthorizationException('Assignment training ini milik pengguna lain.');
        }
    }

    private function assertStatus(TrainingAssignment $assignment, array $allowed): void
    {
        if (! in_array($assignment->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Transisi training tidak valid dari status {$assignment->status}."]);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Permission {$permission} diperlukan.");
        }
    }
}
