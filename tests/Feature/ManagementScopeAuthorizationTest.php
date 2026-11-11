<?php

namespace Tests\Feature;

use App\Filament\Resources\RiskResource;
use App\Models\ComplianceScope;
use App\Models\ManagementSystem;
use App\Models\Risk;
use App\Models\Standard;
use App\Models\User;
use App\Policies\RiskPolicy;
use Database\Seeders\IsoRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_scope_filters_records_without_affecting_global_roles_or_unowned_scopes(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);
        $worker = User::create(['name' => 'Worker', 'email' => 'worker-scope@example.test', 'password' => 'password']);
        $other = User::create(['name' => 'Other', 'email' => 'other-scope@example.test', 'password' => 'password']);
        $manager = User::create(['name' => 'ISO Manager', 'email' => 'manager-scope@example.test', 'password' => 'password']);
        $worker->assignRole('process_owner');
        $manager->assignRole('iso_manager');
        $standard = Standard::create(['code' => 'STANDARD', 'name' => 'Standard']);
        $scopeA = ComplianceScope::create(['code' => 'A', 'name' => 'Scope A', 'owner_id' => $worker->id]);
        $scopeB = ComplianceScope::create(['code' => 'B', 'name' => 'Scope B', 'owner_id' => $other->id]);
        $scopePublic = ComplianceScope::create(['code' => 'C', 'name' => 'Unassigned Scope']);
        $sysA = ManagementSystem::create(['code' => 'MS-A', 'name' => 'System A', 'standard_id' => $standard->id, 'compliance_scope_id' => $scopeA->id]);
        $sysB = ManagementSystem::create(['code' => 'MS-B', 'name' => 'System B', 'standard_id' => $standard->id, 'compliance_scope_id' => $scopeB->id]);
        $sysC = ManagementSystem::create(['code' => 'MS-C', 'name' => 'System C', 'standard_id' => $standard->id, 'compliance_scope_id' => $scopePublic->id]);
        $riskA = Risk::create(['management_system_id' => $sysA->id, 'code' => 'R-A', 'title' => 'Risk A']);
        $riskB = Risk::create(['management_system_id' => $sysB->id, 'code' => 'R-B', 'title' => 'Risk B']);
        $riskC = Risk::create(['management_system_id' => $sysC->id, 'code' => 'R-C', 'title' => 'Risk C']);

        $policy = app(RiskPolicy::class);
        $this->assertTrue($policy->view($worker, $riskA));
        $this->assertFalse($policy->view($worker, $riskB));
        $this->assertTrue($policy->view($worker, $riskC));
        $this->actingAs($worker);
        $visible = RiskResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($riskA->id, $visible);
        $this->assertContains($riskC->id, $visible);
        $this->assertNotContains($riskB->id, $visible);

        $this->assertTrue($policy->view($manager, $riskB));
        $this->actingAs($manager);
        $this->assertCount(3, RiskResource::getEloquentQuery()->get());

        $scopeB->update(['owner_id' => null]);
        $this->actingAs($worker);
        $this->assertContains($riskB->id, RiskResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_direct_record_owner_can_work_with_record_outside_scope_ownership(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);
        $worker = User::create(['name' => 'Worker', 'email' => 'worker2-scope@example.test', 'password' => 'password']);
        $other = User::create(['name' => 'Other', 'email' => 'other2-scope@example.test', 'password' => 'password']);
        $worker->assignRole('process_owner');
        $standard = Standard::create(['code' => 'STANDARD2', 'name' => 'Standard']);
        $scope = ComplianceScope::create(['code' => 'A', 'name' => 'Scope A', 'owner_id' => $other->id]);
        $system = ManagementSystem::create(['code' => 'MS-A', 'name' => 'System A', 'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id]);
        $risk = Risk::create(['management_system_id' => $system->id, 'code' => 'R-OWN', 'title' => 'Assigned risk', 'owner_id' => $worker->id]);
        $this->actingAs($worker);
        $this->assertTrue(app(RiskPolicy::class)->update($worker, $risk));
        $this->assertContains($risk->id, RiskResource::getEloquentQuery()->pluck('id')->all());
    }
}
