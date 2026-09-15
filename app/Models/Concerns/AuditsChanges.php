<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait AuditsChanges
{
    public static function bootAuditsChanges(): void
    {
        static::created(function ($model): void {
            $model->writeActivityLog('created', null, $model->getAttributes());
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
            }

            $model->writeActivityLog('updated', $old, $changes);
        });

        static::deleted(function ($model): void {
            $model->writeActivityLog('deleted', $model->getOriginal(), null);
        });
    }

    protected function writeActivityLog(string $event, ?array $oldValues, ?array $newValues): void
    {
        ActivityLog::create([
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'actor_id' => Auth::id(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
        ]);
    }
}
