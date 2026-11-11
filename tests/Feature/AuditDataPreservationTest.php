<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\ComplianceScope;
use App\Models\CorrectiveAction;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditDataPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_and_verified_evidence_cannot_be_hard_deleted(): void
    {
        $standard = Standard::create(['code' => 'STD', 'name' => 'Standard']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Scope']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'SYS', 'name' => 'System']);
        $audit = Audit::create(['management_system_id' => $system->id, 'code' => 'AUD-X', 'title' => 'Audit X']);
        AuditChecklistItem::create(['audit_id' => $audit->id, 'question' => 'Check', 'result' => 'conforming']);
        $this->assertRejected(fn () => $audit->delete());

        $evidence = Evidence::create([
            'management_system_id' => $system->id, 'title' => 'Signed Report',
            'verification_status' => 'verified', 'status' => 'active',
        ]);
        $this->assertRejected(fn () => $evidence->delete());
        $evidence->update(['status' => 'archived']);
        $this->assertDatabaseHas('evidences', ['id' => $evidence->id, 'status' => 'archived']);
    }

    public function test_finding_cannot_be_reclassified_after_corrective_action_exists(): void
    {
        $standard = Standard::create(['code' => 'STD', 'name' => 'Standard']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Scope']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'SYS', 'name' => 'System']);
        $audit = Audit::create(['management_system_id' => $system->id, 'code' => 'AUD-X', 'title' => 'Audit X']);
        $finding = AuditFinding::create(['audit_id' => $audit->id, 'code' => 'NCR-X', 'title' => 'Finding', 'description' => 'Nonconformity']);
        CorrectiveAction::create(['audit_finding_id' => $finding->id, 'action' => 'Fix the control']);

        $this->assertRejected(fn () => $finding->update(['finding_type' => 'observation']));
        $this->assertDatabaseHas('audit_findings', ['id' => $finding->id, 'finding_type' => 'nonconformity']);
    }

    private function assertRejected(callable $attempt): void
    {
        try {
            $attempt();
            $this->fail('Historical record deletion or reclassification must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
