<?php

namespace Tests\Feature;

use Database\Seeders\IsoRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuidedRoleSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('iso-km.guided_assessment_enabled', true);
    }

    public function test_new_guided_permissions_do_not_reset_existing_custom_role_grants(): void
    {
        Permission::findOrCreate('custom.private.audit', 'web');
        $role = Role::findOrCreate('process_owner', 'web');
        $role->givePermissionTo('custom.private.audit');

        $this->seed(IsoRolePermissionSeeder::class);
        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('custom.private.audit'));
        $this->assertTrue($role->hasPermissionTo('guided.answer.submit'));
        $this->assertTrue($role->hasPermissionTo('guided.risk.approve'));

        $this->seed(IsoRolePermissionSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('custom.private.audit'));
    }
}
