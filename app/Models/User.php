<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Deployment-safe transition: before the permission seeder has run, preserve
        // existing access. Once portal.access exists, permission becomes the gate.
        $permissionExists = Permission::query()->where('guard_name', 'web')->where('name', 'portal.access')->exists();
        return ! $permissionExists || $this->can('portal.access');
    }
}
