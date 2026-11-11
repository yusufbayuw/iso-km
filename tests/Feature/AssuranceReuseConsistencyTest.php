<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\ControlMapping;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Standard;
use App\Models\StatementOfApplicability;
use App\Models\User;
use App\Services\AssuranceAnalysisService;
use App\Services\CrossStandardMappingService;
use App\Services\TraceabilityMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssuranceReuseConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_control_reuse_is_counted_once_and_expires_with_source_evidence(): void
    {
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $stdA = Standard::create(['code' => 'ISO 27001', 'name' => 'ISMS']);
        $stdB = Standard::create(['code' => 'ISO 9001', 'name' => 'QMS']);
        $src = ManagementSystem::create(['standard_id' => $stdA->id, 'compliance_scope_id' => $scope->id, 'code' => 'ISMS', 'name' => 'ISMS']);
        $dst = ManagementSystem::create(['standard_id' => $stdB->id, 'compliance_scope_id' => $scope->id, 'code' => 'QMS', 'name' => 'QMS']);
        $srcControl = Control::create(['standard_id' => $stdA->id, 'code' => 'A.5', 'title' => 'Source policy']);
        $dstControl = Control::create(['standard_id' => $stdB->id, 'code' => '5.1', 'title' => 'Target policy']);
        $req = Requirement::create(['standard_id' => $stdB->id, 'code' => '4', 'title' => 'Context']);
        $req->controls()->attach($dstControl);
        RequirementAssessment::create(['management_system_id' => $dst->id, 'requirement_id' => $req->id, 'status' => 'conformant']);
        StatementOfApplicability::create(['management_system_id' => $dst->id, 'control_id' => $dstControl->id, 'applicable' => true, 'implementation_status' => 'implemented']);

        $actor = User::create(['name' => 'Reviewer', 'email' => 'reviewer@example.test', 'password' => 'password']);
        foreach (['cross_standard_mapping.approve', 'cross_standard_reuse.accept'] as $p) {
            Permission::findOrCreate($p, 'web');
            $actor->givePermissionTo($p);
        }

        $evidence = Evidence::create(['management_system_id' => $src->id, 'title' => 'Signed policy', 'type' => 'record', 'status' => 'active', 'verification_status' => 'verified']);
        $evidence->controls()->attach($srcControl);

        $mapping = ControlMapping::create(['control_a_id' => $srcControl->id, 'control_b_id' => $dstControl->id, 'mapping_type' => 'equivalent']);
        $service = app(CrossStandardMappingService::class);
        $service->approveControl($mapping, $actor);
        $service->acceptControlReuse($evidence, $dst, $dstControl, $mapping->fresh(), $actor);

        $analysis = app(AssuranceAnalysisService::class);
        $this->assertSame('100.00', $analysis->refresh($dst)->evidence_coverage);
        $matrix = app(TraceabilityMatrixService::class)->build($dst);
        $this->assertSame(1, $matrix['rows']->first()['verified_fresh_reused_evidence']);

        $evidence->update(['expires_at' => today()->subDay()]);
        $this->assertSame('0.00', $analysis->refresh($dst)->evidence_coverage);
        $matrix = app(TraceabilityMatrixService::class)->build($dst);
        $this->assertSame(0, $matrix['rows']->first()['verified_fresh_reused_evidence']);

        $evidence->update(['expires_at' => null]);
        $service->rejectControl($mapping->fresh(), $actor);
        $this->assertSame('0.00', $analysis->refresh($dst)->evidence_coverage);
    }
}
