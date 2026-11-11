<?php

namespace Tests\Feature;

use App\Filament\Resources\RiskResource;
use App\Filament\Resources\CompetencyRecordResource;
use App\Models\ComplianceScope;
use App\Models\CompetencyRecord;
use App\Models\ManagementSystem;
use App\Models\Risk;
use App\Models\Standard;
use App\Models\User;
use App\Policies\RiskPolicy;
use App\Services\ModelScopeAuthorizationService;
use Database\Seeders\IsoRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_competency_register_uses_user_id_without_a_nonexistent_owner_column(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);
        $worker = User::create(['name' => 'Worker', 'email' => 'comp-worker@example.test', 'password' => 'password']);
        $other = User::create(['name' => 'Other', 'email' => 'comp-other@example.test', 'password' => 'password']);
        $worker->assignRole('process_owner');
        $standard = Standard::create(['code' => 'STD-COMP', 'name' => 'Training standard']);
        $ownScope = ComplianceScope::create(['code' => 'OWN-C', 'name' => 'Own', 'owner_id' => $worker->id]);
        $otherScope = ComplianceScope::create(['code' => 'OTH-C', 'name' => 'Other', 'owner_id' => $other->id]);
        $own = ManagementSystem::create(['code' => 'MS-OWN-C', 'name' => 'Own', 'standard_id' => $standard->id, 'compliance_scope_id' => $ownScope->id]);
        $foreign = ManagementSystem::create(['code' => 'MS-OTH-C', 'name' => 'Other', 'standard_id' => $standard->id, 'compliance_scope_id' => $otherScope->id]);
        $mine = CompetencyRecord::create(['management_system_id' => $own->id, 'user_id' => $worker->id, 'competency' => 'Own record']);
        $assignedToMe = CompetencyRecord::create(['management_system_id' => $foreign->id, 'user_id' => $worker->id, 'competency' => 'My assigned record']);
        $notMine = CompetencyRecord::create(['management_system_id' => $foreign->id, 'user_id' => $other->id, 'competency' => 'Foreign record']);

        $this->actingAs($worker);
        $visible = CompetencyRecordResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($mine->id, $visible);
        $this->assertContains($assignedToMe->id, $visible);
        $this->assertNotContains($notMine->id, $visible);
    }

    public function test_native_scope_choices_and_model_saves_reject_cross_scope_forgery(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);
        $worker = User::create(['name' => 'Worker', 'email' => 'form-worker@example.test', 'password' => 'password']);
        $other = User::create(['name' => 'Other', 'email' => 'form-other@example.test', 'password' => 'password']);
        $manager = User::create(['name' => 'Manager', 'email' => 'form-manager@example.test', 'password' => 'password']);
        $worker->assignRole('process_owner');
        $manager->assignRole('iso_manager');
        $standard = Standard::create(['code' => 'STD-SCOPE', 'name' => 'Standard']);
        $ownScope = ComplianceScope::create(['code' => 'OWN-S', 'name' => 'Owned', 'owner_id' => $worker->id]);
        $foreignScope = ComplianceScope::create(['code' => 'OTH-S', 'name' => 'Foreign', 'owner_id' => $other->id]);
        $sharedScope = ComplianceScope::create(['code' => 'PUB-S', 'name' => 'Shared']);
        $own = ManagementSystem::create(['code' => 'MS-OWN-S', 'name' => 'Owned system', 'standard_id' => $standard->id, 'compliance_scope_id' => $ownScope->id]);
        $foreign = ManagementSystem::create(['code' => 'MS-OTH-S', 'name' => 'Foreign system', 'standard_id' => $standard->id, 'compliance_scope_id' => $foreignScope->id]);
        $shared = ManagementSystem::create(['code' => 'MS-PUB-S', 'name' => 'Shared system', 'standard_id' => $standard->id, 'compliance_scope_id' => $sharedScope->id]);

        $this->actingAs($worker);
        $access = app(ModelScopeAuthorizationService::class);
        $options = $access->scopeAvailableSystems(ManagementSystem::query(), $worker)->pluck('id')->all();
        $this->assertContains($own->id, $options);
        $this->assertContains($shared->id, $options);
        $this->assertNotContains($foreign->id, $options);

        $this->assertCrossScopeRejected(fn () => Risk::create([
            'management_system_id' => $foreign->id, 'code' => 'R-FORGED', 'title' => 'Unauthorized risk',
        ]), 'management_system_id');
        $this->assertCrossScopeRejected(fn () => ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $foreignScope->id,
            'code' => 'MS-FORGED', 'name' => 'Unauthorized system',
        ]), 'compliance_scope_id');

        $legit = Risk::create(['management_system_id' => $own->id, 'code' => 'R-OWN-S', 'title' => 'Authorized risk']);
        $this->assertCrossScopeRejected(fn () => $legit->update([
            'management_system_id' => $foreign->id,
        ]), 'management_system_id');
        $this->assertSame($own->id, $legit->fresh()->management_system_id);

        $this->actingAs($manager);
        $this->assertContains($foreign->id,
            $access->scopeAvailableSystems(ManagementSystem::query(), $manager)->pluck('id')->all());
        $global = Risk::create(['management_system_id' => $foreign->id, 'code' => 'R-MANAGER', 'title' => 'Global authorized']);
        $this->assertSame($foreign->id, $global->management_system_id);
    }

    private function assertCrossScopeRejected(callable $attempt, string $field): void
    {
        try {
            $attempt();
            $this->fail('Cross-scope write should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
