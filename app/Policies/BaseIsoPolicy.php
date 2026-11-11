<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ModelScopeAuthorizationService;
use Illuminate\Database\Eloquent\Model;

abstract class BaseIsoPolicy
{
    protected string $entity;

    public function viewAny(User $user): bool { return $user->can("view_any_{$this->entity}"); }
    public function view(User $user, Model $model): bool { return $user->can("view_{$this->entity}") && app(ModelScopeAuthorizationService::class)->canAccess($user, $model); }
    public function create(User $user): bool { return $user->can("create_{$this->entity}"); }
    public function update(User $user, Model $model): bool { return $user->can("update_{$this->entity}") && app(ModelScopeAuthorizationService::class)->canAccess($user, $model); }
    public function delete(User $user, Model $model): bool { return $user->can("delete_{$this->entity}") && app(ModelScopeAuthorizationService::class)->canAccess($user, $model); }
    public function deleteAny(User $user): bool { return $user->can("delete_any_{$this->entity}"); }
    public function forceDelete(User $user, Model $model): bool { return $user->can("force_delete_{$this->entity}") && app(ModelScopeAuthorizationService::class)->canAccess($user, $model); }
    public function forceDeleteAny(User $user): bool { return $user->can("force_delete_any_{$this->entity}"); }
    public function restore(User $user, Model $model): bool { return $user->can("restore_{$this->entity}") && app(ModelScopeAuthorizationService::class)->canAccess($user, $model); }
    public function restoreAny(User $user): bool { return $user->can("restore_any_{$this->entity}"); }
    public function replicate(User $user, Model $model): bool { return $user->can("replicate_{$this->entity}") && app(ModelScopeAuthorizationService::class)->canAccess($user, $model); }
    public function reorder(User $user): bool { return $user->can("reorder_{$this->entity}"); }
}
