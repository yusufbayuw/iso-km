<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Standard;
use App\Models\StatementOfApplicability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManagementSystemIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_standard_requirement_control_and_audit_items_are_rejected(): void
    {
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $standardA = Standard::create(['code' => 'ISO 27001', 'name' => 'ISMS']);
        $standardB = Standard::create(['code' => 'ISO 9001', 'name' => 'QMS']);
        $system = ManagementSystem::create([
            'standard_id' => $standardA->id, 'compliance_scope_id' => $scope->id, 'code' => 'ISMS', 'name' => 'ISMS',
        ]);
        $reqA = Requirement::create(['standard_id' => $standardA->id, 'code' => '4', 'title' => 'Context']);
        $reqB = Requirement::create(['standard_id' => $standardB->id, 'code' => '4', 'title' => 'Other context']);
        $controlA = Control::create(['standard_id' => $standardA->id, 'code' => '5.1', 'title' => 'Policy']);
        $controlB = Control::create(['standard_id' => $standardB->id, 'code' => '5.1', 'title' => 'Other policy']);

        RequirementAssessment::create(['management_system_id' => $system->id, 'requirement_id' => $reqA->id]);
        StatementOfApplicability::create(['management_system_id' => $system->id, 'control_id' => $controlA->id]);

        $this->assertRejected(fn () => RequirementAssessment::create(['management_system_id' => $system->id, 'requirement_id' => $reqB->id]), 'requirement_id');
        $this->assertRejected(fn () => StatementOfApplicability::create(['management_system_id' => $system->id, 'control_id' => $controlB->id]), 'control_id');

        $audit = Audit::create(['management_system_id' => $system->id, 'code' => 'AUD-001', 'title' => 'Audit']);
        AuditChecklistItem::create(['audit_id' => $audit->id, 'requirement_id' => $reqA->id, 'control_id' => $controlA->id, 'question' => 'Verify policy']);
        $this->assertRejected(fn () => AuditChecklistItem::create(['audit_id' => $audit->id, 'requirement_id' => $reqB->id, 'question' => 'Wrong']), 'requirement_id');
        $this->assertRejected(fn () => AuditFinding::create(['audit_id' => $audit->id, 'code' => 'NCR-001', 'title' => 'Wrong', 'description' => 'Wrong', 'control_id' => $controlB->id]), 'control_id');
    }

    private function assertRejected(callable $attempt, string $field): void
    {
        try {
            $attempt();
            $this->fail('Data lintas standar seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
