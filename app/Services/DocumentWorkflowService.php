<?php

namespace App\Services;

use App\Models\DocumentDistribution;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Notifications\IsoWorkflowNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentWorkflowService
{
    public function submitForReview(DocumentRevision $revision, User $actor): DocumentRevision
    {
        $this->authorize($actor, 'document.submit_review');
        $this->assertStatus($revision, ['draft', 'rejected']);

        if (! $revision->reviewer_id) {
            throw ValidationException::withMessages(['reviewer_id' => 'Reviewer wajib ditentukan sebelum dokumen diajukan untuk review.']);
        }

        $revision->update([
            'status' => 'in_review',
            'prepared_by' => $revision->prepared_by ?: $actor->id,
            'submitted_for_review_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejected_at' => null,
        ]);

        $revision->reviewer?->notify(new IsoWorkflowNotification(
            'document_review',
            'Dokumen menunggu review',
            "{$revision->document->code} revisi {$revision->revision} diajukan untuk review.",
        ));

        return $revision->refresh();
    }

    public function review(DocumentRevision $revision, User $actor, ?string $notes = null): DocumentRevision
    {
        $this->authorize($actor, 'document.review');
        $this->assertStatus($revision, ['in_review']);

        if ($revision->reviewer_id && $revision->reviewer_id !== $actor->id && ! $actor->can('document.review_any')) {
            throw new AuthorizationException('Dokumen ini ditugaskan kepada reviewer lain.');
        }

        $revision->update([
            'status' => 'reviewed',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
            'rejected_at' => null,
        ]);

        $revision->approver?->notify(new IsoWorkflowNotification(
            'document_approval',
            'Dokumen menunggu approval',
            "{$revision->document->code} revisi {$revision->revision} selesai direview dan menunggu approval.",
        ));

        return $revision->refresh();
    }

    public function reject(DocumentRevision $revision, User $actor, string $notes): DocumentRevision
    {
        $this->authorize($actor, 'document.review');
        $this->assertStatus($revision, ['in_review', 'reviewed']);

        $revision->update([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejected_at' => now(),
            'review_notes' => $notes,
        ]);

        $recipient = $revision->preparer ?: $revision->document->owner;
        $recipient?->notify(new IsoWorkflowNotification(
            'document_rejected',
            'Dokumen dikembalikan',
            "{$revision->document->code} revisi {$revision->revision} dikembalikan. Catatan: {$notes}",
        ));

        return $revision->refresh();
    }

    public function approve(DocumentRevision $revision, User $actor): DocumentRevision
    {
        $this->authorize($actor, 'document.approve');
        $this->assertStatus($revision, ['reviewed']);

        if (! $revision->approver_id) {
            throw ValidationException::withMessages(['approver_id' => 'Approver wajib ditentukan sebelum dokumen disetujui.']);
        }

        if ($revision->approver_id !== $actor->id && ! $actor->can('document.approve_any')) {
            throw new AuthorizationException('Dokumen ini ditugaskan kepada approver lain.');
        }

        $revision->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $revision->document->owner?->notify(new IsoWorkflowNotification(
            'document_approved',
            'Dokumen telah disetujui',
            "{$revision->document->code} revisi {$revision->revision} telah disetujui dan siap diberlakukan.",
        ));

        return $revision->refresh();
    }

    public function makeEffective(DocumentRevision $revision, User $actor, ?string $effectiveDate = null): DocumentRevision
    {
        $this->authorize($actor, 'document.make_effective');
        $this->assertStatus($revision, ['approved']);

        return DB::transaction(function () use ($revision, $effectiveDate): DocumentRevision {
            DocumentRevision::query()
                ->where('document_id', $revision->document_id)
                ->where('is_current', true)
                ->where('id', '!=', $revision->getKey())
                ->get()
                ->each(function (DocumentRevision $current): void {
                    $current->update([
                        'status' => 'obsolete',
                        'is_current' => false,
                        'obsolete_at' => today(),
                    ]);
                });

            DocumentDistribution::query()
                ->where('document_id', $revision->document_id)
                ->where('document_revision_id', '!=', $revision->id)
                ->where('status', 'pending')
                ->update(['status' => 'superseded']);

            $revision->update([
                'status' => 'effective',
                'effective_at' => $effectiveDate ?: today(),
                'obsolete_at' => null,
                'is_current' => true,
            ]);

            $revision->document->update([
                'status' => 'effective',
                'next_review_at' => today()->addMonths($revision->document->review_frequency_months ?: 12),
            ]);

            $userIds = $revision->document->distributions()->pluck('user_id')->unique()->values();

            foreach ($userIds as $userId) {
                $distribution = DocumentDistribution::query()->updateOrCreate([
                    'document_id' => $revision->document_id,
                    'user_id' => $userId,
                    'document_revision_id' => $revision->id,
                ], [
                    'distributed_at' => now(),
                    'acknowledgement_due_at' => today()->addDays(14),
                    'acknowledged_at' => null,
                    'status' => 'pending',
                ]);

                $distribution->user?->notify(new IsoWorkflowNotification(
                    'document_distribution',
                    'Controlled document baru',
                    "{$revision->document->code} revisi {$revision->revision} telah berlaku. Mohon baca dan lakukan acknowledgement.",
                ));
            }

            return $revision->refresh();
        });
    }

    public function acknowledge(DocumentDistribution $distribution, User $actor): DocumentDistribution
    {
        $this->authorize($actor, 'document.acknowledge');

        if ($distribution->user_id !== $actor->id) {
            throw new AuthorizationException('Controlled copy ini didistribusikan kepada pengguna lain.');
        }

        if ($distribution->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Dokumen ini tidak lagi menunggu acknowledgement.']);
        }

        if ($distribution->revision?->status !== 'effective') {
            throw ValidationException::withMessages(['revision' => 'Hanya controlled copy yang masih efektif yang dapat di-acknowledge.']);
        }

        $distribution->update([
            'acknowledged_at' => now(),
            'status' => 'acknowledged',
        ]);

        return $distribution->refresh();
    }

    public function obsolete(DocumentRevision $revision, User $actor): DocumentRevision
    {
        $this->authorize($actor, 'document.obsolete');
        $this->assertStatus($revision, ['effective']);

        return DB::transaction(function () use ($revision): DocumentRevision {
            $revision->update([
                'status' => 'obsolete',
                'is_current' => false,
                'obsolete_at' => today(),
            ]);

            DocumentDistribution::query()
                ->where('document_revision_id', $revision->id)
                ->where('status', 'pending')
                ->update(['status' => 'superseded']);

            $revision->document->update([
                'status' => 'obsolete',
                'next_review_at' => null,
            ]);

            return $revision->refresh();
        });
    }

    private function assertStatus(DocumentRevision $revision, array $allowed): void
    {
        if (! in_array($revision->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Transisi tidak valid dari status {$revision->status}."]);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Permission {$permission} diperlukan.");
        }
    }
}
