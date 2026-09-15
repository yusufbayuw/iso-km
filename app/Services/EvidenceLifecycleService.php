<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\EvidenceRequest;
use App\Models\EvidenceSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvidenceLifecycleService
{
    public function generateDueRequests(): int
    {
        $created = 0;

        EvidenceSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_at')
            ->get()
            ->each(function (EvidenceSchedule $schedule) use (&$created): void {
                $horizon = today()->addDays($schedule->lead_days ?: 0);
                $due = CarbonImmutable::parse($schedule->next_due_at);
                $guard = 0;

                while ($due->lte($horizon) && $guard < 24) {
                    $request = EvidenceRequest::query()->firstOrCreate(
                        ['evidence_schedule_id' => $schedule->id, 'due_at' => $due->toDateString()],
                        [
                            'management_system_id' => $schedule->management_system_id,
                            'owner_id' => $schedule->owner_id,
                            'period_label' => $this->periodLabel($schedule, $due),
                            'status' => $due->isPast() ? 'overdue' : 'requested',
                            'requested_at' => now(),
                        ],
                    );

                    if ($request->wasRecentlyCreated) {
                        $created++;
                    }

                    $due = $this->nextDueDate($schedule, $due);
                    $guard++;
                }

                if (! CarbonImmutable::parse($schedule->next_due_at)->equalTo($due)) {
                    $schedule->update(['next_due_at' => $due->toDateString()]);
                }
            });

        return $created;
    }

    public function refreshEvidenceStatuses(): int
    {
        $expired = Evidence::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today())
            ->whereNotIn('status', ['expired', 'archived'])
            ->update(['status' => 'expired']);

        EvidenceRequest::query()
            ->whereIn('status', ['requested', 'submitted'])
            ->whereDate('due_at', '<', today())
            ->update(['status' => 'overdue']);

        return $expired;
    }

    public function submit(EvidenceRequest $request, Evidence $evidence, User $actor): EvidenceRequest
    {
        $this->authorizeOwnerOr($request, $actor, 'evidence.manage');

        if (in_array($request->status, ['verified', 'waived'], true)) {
            throw ValidationException::withMessages(['status' => 'Evidence request ini sudah ditutup.']);
        }

        return DB::transaction(function () use ($request, $evidence, $actor): EvidenceRequest {
            $schedule = $request->schedule()->with(['requirements', 'controls'])->first();

            $evidence->update([
                'management_system_id' => $request->management_system_id,
                'owner_id' => $evidence->owner_id ?: $actor->id,
                'status' => 'active',
                'verification_status' => 'pending',
                'captured_at' => $evidence->captured_at ?: today(),
                'expires_at' => $evidence->expires_at ?: ($schedule?->validity_days ? today()->addDays($schedule->validity_days) : null),
            ]);

            if ($schedule) {
                $evidence->requirements()->syncWithoutDetaching($schedule->requirements->modelKeys());
                $evidence->controls()->syncWithoutDetaching($schedule->controls->modelKeys());
            }

            $request->update(['evidence_id' => $evidence->id, 'status' => 'submitted', 'submitted_at' => now()]);

            return $request->refresh();
        });
    }

    public function verify(EvidenceRequest $request, User $actor, ?string $notes = null): EvidenceRequest
    {
        if (! $actor->can('evidence.verify')) {
            throw new AuthorizationException('Permission evidence.verify diperlukan.');
        }

        if (! $request->evidence_id || ! in_array($request->status, ['submitted', 'overdue'], true)) {
            throw ValidationException::withMessages(['status' => 'Evidence harus disubmit sebelum diverifikasi.']);
        }

        return DB::transaction(function () use ($request, $actor, $notes): EvidenceRequest {
            $request->evidence->update([
                'verification_status' => 'verified',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_notes' => $notes,
                'status' => 'active',
            ]);

            $request->update([
                'status' => 'verified',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_notes' => $notes,
            ]);

            return $request->refresh();
        });
    }

    private function nextDueDate(EvidenceSchedule $schedule, CarbonImmutable $due): CarbonImmutable
    {
        return match ($schedule->frequency) {
            'weekly' => $due->addWeek(),
            'monthly' => $due->addMonthNoOverflow(),
            'quarterly' => $due->addMonthsNoOverflow(3),
            'semiannual' => $due->addMonthsNoOverflow(6),
            'annual' => $due->addYearNoOverflow(),
            'custom' => $due->addDays(max(1, (int) $schedule->interval_days)),
            default => $due->addMonthNoOverflow(),
        };
    }

    private function periodLabel(EvidenceSchedule $schedule, CarbonImmutable $due): string
    {
        return match ($schedule->frequency) {
            'weekly' => $due->format('o-\\WW'),
            'monthly' => $due->format('Y-m'),
            'quarterly' => $due->year.'-Q'.(int) ceil($due->month / 3),
            'semiannual' => $due->year.'-H'.($due->month <= 6 ? '1' : '2'),
            'annual' => $due->format('Y'),
            default => $due->format('Y-m-d'),
        };
    }

    private function authorizeOwnerOr(EvidenceRequest $request, User $actor, string $permission): void
    {
        if ($request->owner_id !== $actor->id && ! $actor->can($permission)) {
            throw new AuthorizationException("Permission {$permission} diperlukan.");
        }
    }
}
