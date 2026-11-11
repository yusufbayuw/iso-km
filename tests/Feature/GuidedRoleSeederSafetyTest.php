<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_first_time_permission_seeding_preserves_existing_panel_access(): void
    {
        $existingUser = User::create([
            'name' => 'Pre-existing operator', 'email' => 'existing@example.test',
            'password' => 'password',
        ]);

        $this->seed(IsoRolePermissionSeeder::class);

        $this->assertTrue($existingUser->fresh()->can('portal.access'));

        $futureUser = User::create([
            'name' => 'New operator', 'email' => 'future@example.test',
            'password' => 'password',
        ]);
        $this->assertFalse($futureUser->can('portal.access'),
            'Users created after the permission gate exists need an explicit grant or suitable role.');
    }

    public function test_existing_gate_backfill_is_explicit_and_opt_in(): void
    {
        Permission::findOrCreate('portal.access', 'web');
        $existingUser = User::create([
            'name' => 'Locked out operator', 'email' => 'locked@example.test',
            'password' => 'password',
        ]);
        $this->seed(IsoRolePermissionSeeder::class);
        $this->assertFalse($existingUser->fresh()->can('portal.access'));

        config()->set('iso-km.role_seeder_backfill_existing_user_access', true);
        $this->seed(IsoRolePermissionSeeder::class);
        $this->assertTrue($existingUser->fresh()->can('portal.access'));
    }

    public function test_new_coordinator_and_guided_workflow_presets_are_least_privileged(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);

        $coordinator = Role::findByName('iso_coordinator', 'web');
        $this->assertTrue($coordinator->hasPermissionTo('guided.assessment.manage'));
        $this->assertTrue($coordinator->hasPermissionTo('guided.consolidate'));
        $this->assertFalse($coordinator->hasPermissionTo('delete_standard'));
        $this->assertFalse($coordinator->hasPermissionTo('guided.management.approve'));
        $this->assertFalse($coordinator->hasPermissionTo('guided.risk.approve'));
        $this->assertFalse($coordinator->hasPermissionTo('guided.system.manage_all'));
        $this->assertFalse($coordinator->hasPermissionTo('update_role'));

        $assessor = Role::findByName('guided_assessor', 'web');
        $this->assertTrue($assessor->hasPermissionTo('guided.assessment.review'));
        $this->assertTrue($assessor->hasPermissionTo('view_evidence'));
        $this->assertFalse($assessor->hasPermissionTo('guided.management.approve'));

        $consolidator = Role::findByName('guided_consolidator', 'web');
        $this->assertTrue($consolidator->hasPermissionTo('guided.consolidate'));
        $this->assertFalse($consolidator->hasPermissionTo('guided.assessment.manage'));

        $reviewer = Role::findByName('guided_evidence_reviewer', 'web');
        $this->assertTrue($reviewer->hasPermissionTo('guided.evidence.review'));
        $this->assertTrue($reviewer->hasPermissionTo('view_evidence'));
        $this->assertFalse($reviewer->hasPermissionTo('guided.management.approve'));

        $this->assertTrue(Role::findByName('guided_risk_owner', 'web')
            ->hasPermissionTo('guided.risk.approve'));
        $this->assertTrue(Role::findByName('guided_management_approver', 'web')
            ->hasPermissionTo('guided.management.approve'));
        $this->assertTrue(Role::findByName('guided_respondent', 'web')
            ->hasPermissionTo('guided.answer.submit'));

        $admin = Role::findByName('super_admin', 'web');
        $this->assertTrue($admin->hasPermissionTo('view_any_role'));
        $this->assertTrue($admin->hasPermissionTo('update_role'));
        $this->assertTrue($admin->hasPermissionTo('guided.template.manage_global'));
    }

    public function test_existing_role_baselines_can_be_reconciled_additively_but_never_revoked(): void
    {
        Permission::findOrCreate('custom.special', 'web');
        $role = Role::findOrCreate('training_coordinator', 'web');
        $role->givePermissionTo('custom.special');

        $this->seed(IsoRolePermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('custom.special'));
        $this->assertTrue($role->fresh()->hasPermissionTo('portal.access'));
        $this->assertFalse($role->fresh()->hasPermissionTo('training.manage'),
            'New non-Guided grants for existing roles require explicit reconciliation.');

        config()->set('iso-km.role_seeder_reconcile_existing', true);
        $this->seed(IsoRolePermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('training.manage'));
        $this->assertTrue($role->fresh()->hasPermissionTo('custom.special'));
    }

    public function test_existing_legacy_coordinator_permissions_are_not_revoked_automatically(): void
    {
        Permission::findOrCreate('delete_standard', 'web');
        $role = Role::findOrCreate('iso_coordinator', 'web');
        $role->givePermissionTo('delete_standard');

        $this->seed(IsoRolePermissionSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('delete_standard'),
            'A deliberate admin review, not production seeding, should remove historic role grants.');
    }
}
