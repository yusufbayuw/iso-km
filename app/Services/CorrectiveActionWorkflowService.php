<?php

namespace App\Services;

use App\Models\AuditFinding;
use App\Models\CorrectiveAction;
use App\Models\User;
use App\Services\ModelScopeAuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectiveActionWorkflowService
{
    public function submitForVerification(CorrectiveAction $action, User $actor): void
    {
        // Newly created Eloquent instances may not have loaded DB defaults.
        $action = $action->fresh() ?? $action;
        if (! app(ModelScopeAuthorizationService::class)->canAccess($actor, $action)) {
            throw ValidationException::withMessages(['authorization' => 'CAPA berada di luar lingkup kewenangan Anda.']);
        }
        if ((int) $action->owner_id !== (int) $actor->id && ! $actor->can('capa.manage')) {
            throw ValidationException::withMessages(['authorization' => 'Anda tidak berwenang menyerahkan tindakan perbaikan ini.']);
        }
        if (! in_array($action->status, ['open', 'in_progress'], true)) {
            throw ValidationException::withMessages(['status' => 'Tindakan ini tidak sedang dalam tahap pengerjaan.']);
        }

        DB::transaction(function () use ($action, $actor): void {
            $action->update([
                'status' => 'verification',
                // Auto-assign responsibility when the original CAPA had no PIC.
                // The submitter may no longer be their own verifier.
                'owner_id' => $action->owner_id ?? $actor->id,
                'completed_at' => now(),
                'effectiveness_status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'verification_notes' => null,
                'effectiveness_notes' => null,
            ]);
        });
    }

    public function verify(CorrectiveAction $action, User $actor, bool $effective, string $notes): void
    {
        $action = $action->fresh() ?? $action;
        if (! $actor->can('capa.verify')) {
            throw ValidationException::withMessages(['authorization' => 'Anda tidak memiliki kewenangan verifikasi CAPA.']);
        }
        if (! app(ModelScopeAuthorizationService::class)->canAccess($actor, $action)) {
            throw ValidationException::withMessages(['authorization' => 'CAPA berada di luar lingkup kewenangan Anda.']);
        }
        if ($action->owner_id === null) {
            throw ValidationException::withMessages(['owner_id' => 'CAPA perlu memiliki PIC sebelum diverifikasi.']);
        }
        if ((int) $action->owner_id === (int) $actor->id) {
            throw ValidationException::withMessages(['authorization' => 'Pelaksana tidak boleh memverifikasi tindakan sendiri.']);
        }
        if ($action->status !== 'verification' || blank(trim($notes))) {
            throw ValidationException::withMessages(['status' => 'Tindakan belum siap diverifikasi atau catatan belum diisi.']);
        }

        DB::transaction(function () use ($action, $actor, $effective, $notes): void {
            $action->update([
                'status' => $effective ? 'completed' : 'in_progress',
                'effectiveness_status' => $effective ? 'effective' : 'ineffective',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_notes' => trim($notes),
                'effectiveness_notes' => trim($notes),
                'completed_at' => $effective ? $action->completed_at ?? now() : null,
            ]);
        });
    }

    public function syncFinding(AuditFinding $finding): void
    {
        // CAPA actions can update the same finding through another model
        // instance. Always reconcile against the persisted current state.
        $finding = $finding->fresh() ?? $finding;
        if ($finding->finding_type !== 'nonconformity') {
            return;
        }

        $actions = $finding->correctiveActions()->get();
        if ($actions->isEmpty()) {
            // Historical/manual findings without CAPA remain untouched.
            return;
        }

        $allEffective = $actions->every(fn (CorrectiveAction $action): bool =>
            $action->status === 'completed'
            && $action->effectiveness_status === 'effective'
            && $action->verified_by !== null
            && $action->verified_at !== null
        );
        $readyToClose = $allEffective && filled($finding->root_cause);
        // Verification is the aggregate stage only when NO CAPA is still
        // waiting for execution. One submitted CAPA must not mask open work.
        $readyForAggregateVerification = $actions->every(fn (CorrectiveAction $action): bool =>
            $action->status === 'verification'
            || ($action->status === 'completed'
                && $action->effectiveness_status === 'effective'
                && $action->verified_by !== null
                && $action->verified_at !== null)
        );
        $nextStatus = $readyToClose
            ? 'closed'
            : ($readyForAggregateVerification ? 'verification' : 'action');

        if ($finding->status !== $nextStatus) {
            // Preserve all actions/history; only project the aggregate status.
            $finding->update(['status' => $nextStatus]);
        }
    }

    public function assertCanClose(AuditFinding $finding): void
    {
        if ($finding->finding_type !== 'nonconformity') {
            return;
        }

        if (blank($finding->root_cause)) {
            throw ValidationException::withMessages(['root_cause' => 'Catat akar penyebab sebelum finding ditutup.']);
        }

        $actions = $finding->correctiveActions()->get();
        if ($actions->isEmpty() || ! $actions->every(fn (CorrectiveAction $action): bool =>
            $action->status === 'completed'
            && $action->effectiveness_status === 'effective'
            && $action->verified_by !== null
            && $action->verified_at !== null
        )) {
            throw ValidationException::withMessages(['status' => 'Finding hanya dapat ditutup setelah seluruh CAPA diverifikasi efektif.']);
        }
    }
}
