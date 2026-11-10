<?php

namespace App\Services;

use App\Models\GuidedAssessment;
use App\Models\GuidedExportTemplate;
use App\Models\ManagementSystem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class GuidedSystemAccessService
{
    /**
     * Explicit scope owners can start assessments; already assigned
     * coordinators can work on their existing management systems.
     * A separate privileged permission grants global administration.
     */
    public function accessibleSystems(User $user): Builder
    {
        $query = ManagementSystem::query();

        if ($user->can('guided.system.manage_all')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->whereHas('scope', fn (Builder $scope) => $scope->where('owner_id', $user->id))
                ->orWhereIn('id', GuidedAssessment::query()
                    ->whereHas('memberships', fn (Builder $membership) => $membership
                        ->where('user_id', $user->id)->where('role', 'coordinator'))
                    ->select('management_system_id'));
        });
    }

    public function canAccess(User $user, int $systemId): bool
    {
        return $this->accessibleSystems($user)->whereKey($systemId)->exists();
    }

    public function assertCanAccess(User $user, int $systemId): void
    {
        if (! $this->canAccess($user, $systemId)) {
            throw ValidationException::withMessages([
                'management_system_id' => 'Sistem manajemen di luar kewenangan pengguna.',
            ]);
        }
    }

    public function canManageTemplate(User $user, GuidedExportTemplate $template): bool
    {
        if (! $user->can('guided.template.manage')) {
            return false;
        }

        if ($template->management_system_id === null) {
            return $user->can('guided.template.manage_global');
        }

        return $this->canAccess($user, (int) $template->management_system_id);
    }

    public function assertCanManageTemplate(User $user, GuidedExportTemplate $template): void
    {
        if (! $this->canManageTemplate($user, $template)) {
            throw ValidationException::withMessages([
                'management_system_id' => 'Tidak berwenang mengelola template pada sistem tersebut.',
            ]);
        }
    }

    public function scopedTemplateQuery(User $user): Builder
    {
        $query = GuidedExportTemplate::query();

        if (! $user->can('guided.template.manage')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->whereIn('management_system_id', $this->accessibleSystems($user)->select('id'));
            if ($user->can('guided.template.manage_global')) {
                $query->orWhereNull('management_system_id');
            }
        });
    }
}
