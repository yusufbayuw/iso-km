<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\ComplianceScope;
use App\Models\CorrectiveAction;
use App\Models\ManagementSystem;
use App\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCapaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_finding_can_have_corrective_actions_and_effectiveness_verification(): void
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'ISMS', 'name' => 'ISMS']);
        $audit = Audit::create(['management_system_id' => $system->id, 'code' => 'AUD-001', 'title' => 'Internal Audit']);
        $finding = AuditFinding::create(['audit_id' => $audit->id, 'code' => 'NCR-001', 'title' => 'Missing evidence', 'description' => 'Required evidence is unavailable.']);
        $action = CorrectiveAction::create(['audit_finding_id' => $finding->id, 'action' => 'Create and verify evidence', 'status' => 'verification', 'effectiveness_status' => 'pending']);

        $this->assertTrue($finding->audit->is($audit));
        $this->assertTrue($finding->correctiveActions->contains($action));
        $this->assertSame('pending', $action->effectiveness_status);
    }
}
