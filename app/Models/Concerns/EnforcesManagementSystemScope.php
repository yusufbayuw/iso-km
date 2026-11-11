<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\ModelScopeAuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * For user-initiated Eloquent writes, enforce the same scope as Filament
 * dropdowns. Console seeders/scheduled jobs without an actor keep working.
 */
trait EnforcesManagementSystemScope
{
    protected static function bootEnforcesManagementSystemScope(): void
    {
        static::saving(function (Model $record): void {
            $actor = auth()->user();
            if (! $actor instanceof User || ! $record->isDirty('management_system_id')) {
                return;
            }

            $systemId = $record->getAttribute('management_system_id');
            if (! $systemId) {
                return; // Existing required validation/DB constraints handle null.
            }

            $access = app(ModelScopeAuthorizationService::class);
            if ($record->exists) {
                $oldSystemId = $record->getRawOriginal('management_system_id');
                $oldOwnerId = $record->getRawOriginal('owner_id');
                $recordOwner = $oldOwnerId !== null && (int) $oldOwnerId === (int) $actor->id;

                if ($oldSystemId && ! $recordOwner
                    && ! $access->canCreateInSystem($actor, (int) $oldSystemId)) {
                    throw ValidationException::withMessages([
                        'management_system_id' => 'Anda tidak berwenang memindahkan data dari sistem asal.',
                    ]);
                }
            }

            if (! $access->canCreateInSystem($actor, (int) $systemId)) {
                throw ValidationException::withMessages([
                    'management_system_id' => 'Sistem manajemen ini di luar lingkup kewenangan Anda.',
                ]);
            }
        });
    }
}
