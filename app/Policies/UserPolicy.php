<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    private function canAdminister(User $user, string $permission): bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
            && $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->canAdminister($user, 'view_any_user');
    }

    public function view(User $user, User $record): bool
    {
        return $this->canAdminister($user, 'view_user');
    }

    public function create(User $user): bool
    {
        return $this->canAdminister($user, 'create_user');
    }

    public function update(User $user, User $record): bool
    {
        return $this->canAdminister($user, 'update_user');
    }

    // Preserve audit references: disable user deletion, prefer is_active=false.
    public function delete(User $user, User $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, User $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, User $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, User $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}
