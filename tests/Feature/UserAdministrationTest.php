<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use Database\Seeders\IsoRolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IsoRolePermissionSeeder::class);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'name' => 'Example User',
            'email' => $email,
            'username' => strstr($email, '@', true),
            'password' => 'some-long-password',
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_receives_user_and_shield_role_permissions_idempotently(): void
    {
        $role = Role::findByName('super_admin', 'web');

        foreach (['portal.access', 'view_any_user', 'create_user', 'update_user', 'view_any_role', 'create_role', 'update_role'] as $name) {
            $this->assertTrue($role->hasPermissionTo($name), $name);
        }

        $coordinator = Role::findByName('iso_coordinator', 'web');
        $this->assertFalse($coordinator->hasPermissionTo('view_any_user'));
        $this->assertFalse($coordinator->hasPermissionTo('view_any_role'));

        $this->seed(IsoRolePermissionSeeder::class);
        $this->assertSame(1, Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->count());
        $this->assertTrue($role->fresh()->hasPermissionTo('view_any_role'));
    }

    public function test_super_admin_can_open_native_user_and_shield_role_pages(): void
    {
        $this->actingAs($this->makeUser('super@example.test', 'super_admin'));

        $this->get(UserResource::getUrl('index'))->assertOk();
        $this->get(RoleResource::getUrl('index'))->assertOk();
    }

    public function test_non_admin_cannot_open_user_or_role_pages_even_with_direct_permissions(): void
    {
        $user = $this->makeUser('ordinary@example.test', 'employee');
        $user->givePermissionTo('view_any_user', 'view_any_role');
        $this->actingAs($user);

        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(RoleResource::getUrl('index'))->assertForbidden();
    }

    public function test_shield_protects_super_admin_role_from_changes_and_bulk_deletion(): void
    {
        $actor = $this->makeUser('admin@example.test', 'super_admin');
        $protected = Role::findByName('super_admin', 'web');
        $ordinary = Role::findByName('employee', 'web');
        $policy = app(RolePolicy::class);

        $this->assertTrue($policy->viewAny($actor));
        $this->assertTrue($policy->update($actor, $ordinary));
        $this->assertFalse($policy->update($actor, $protected));
        $this->assertFalse($policy->delete($actor, $protected));
        $this->assertFalse($policy->deleteAny($actor));
    }

    public function test_accounts_cannot_be_deleted_and_inactive_admin_is_blocked(): void
    {
        $admin = $this->makeUser('admin2@example.test', 'super_admin');
        $target = $this->makeUser('target@example.test', 'employee');
        $policy = app(UserPolicy::class);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->update($admin, $target));
        $this->assertFalse($policy->delete($admin, $target));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('portal')));

        $admin->update(['is_active' => false]);
        $this->assertFalse($admin->fresh()->canAccessPanel(Filament::getPanel('portal')));
    }
}
