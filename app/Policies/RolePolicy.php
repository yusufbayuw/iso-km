<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    private function canAdminister(User $user, string $permission): bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            && $user->can($permission);
    }

    private function isProtected(Role $role): bool
    {
        return $role->name === config('filament-shield.super_admin.name', 'super_admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->canAdminister($user, 'view_any_role');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->canAdminister($user, 'view_role');
    }

    public function create(User $user): bool
    {
        return $this->canAdminister($user, 'create_role');
    }

    public function update(User $user, Role $role): bool
    {
        return ! $this->isProtected($role) && $this->canAdminister($user, 'update_role');
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $this->isProtected($role) && $this->canAdminister($user, 'delete_role');
    }

    // Shield offers bulk deletion; never allow a batch containing super_admin.
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, Role $role): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, Role $role): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, Role $role): bool { return false; }
    public function reorder(User $user): bool { return false; }
}
