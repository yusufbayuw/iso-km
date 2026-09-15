<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Risk;
use App\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsoManagementCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_can_have_scoped_management_system_requirements_and_controls(): void
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'HQ', 'name' => 'Head Office']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'ISMS-HQ', 'name' => 'ISMS Head Office']);
        $requirement = Requirement::create(['standard_id' => $standard->id, 'code' => '4.1', 'title' => 'Context']);
        $control = Control::create(['standard_id' => $standard->id, 'code' => 'A.5.1', 'title' => 'Policy control']);
        $control->requirements()->attach($requirement);

        $this->assertTrue($system->standard->is($standard));
        $this->assertTrue($system->scope->is($scope));
        $this->assertTrue($control->requirements->contains($requirement));
    }

    public function test_risk_scores_are_calculated_on_save(): void
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'ISMS', 'name' => 'ISMS']);
        $risk = Risk::create(['management_system_id' => $system->id, 'code' => 'RISK-001', 'title' => 'Test Risk', 'likelihood' => 4, 'impact' => 5, 'residual_likelihood' => 2, 'residual_impact' => 3]);

        $this->assertSame(20, $risk->inherent_score);
        $this->assertSame(6, $risk->residual_score);
    }
}
