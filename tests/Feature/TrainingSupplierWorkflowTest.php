<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\ComplianceScope;
use App\Models\CompetencyRecord;
use App\Models\Control;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Standard;
use App\Models\StatementOfApplicability;
use App\Models\Supplier;
use App\Models\SupplierAssessment;
use App\Models\TrainingAssignment;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ActionItemSyncService;
use App\Services\AssuranceAnalysisService;
use App\Services\AuditPackService;
use App\Services\TraceabilityMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class TrainingSupplierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_and_supplier_domains_flow_into_work_assurance_and_traceability(): void
    {
        [$system, $requirement, $control] = $this->createSystem();
        $user = User::factory()->create();

        $program = TrainingProgram::create([
            'management_system_id' => $system->id,
            'code' => 'TRN-001',
            'title' => 'Security Awareness',
            'mandatory' => true,
            'status' => 'active',
        ]);
        $program->requirements()->attach($requirement);
        $program->controls()->attach($control);
        TrainingAssignment::create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'cycle_key' => '2026-Q3',
            'assigned_at' => today()->subWeek(),
            'due_at' => today()->subDay(),
            'status' => 'assigned',
        ]);

        $competency = CompetencyRecord::create([
            'management_system_id' => $system->id,
            'user_id' => $user->id,
            'competency' => 'Information Security Awareness',
            'status' => 'active',
            'valid_until' => today()->addDays(10),
        ]);
        $competency->requirements()->attach($requirement);
        $competency->controls()->attach($control);

        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'name' => 'Critical Cloud Provider',
            'owner_id' => $user->id,
            'criticality' => 'critical',
            'status' => 'active',
            'next_review_at' => today()->addDays(7),
        ]);
        $supplier->managementSystems()->attach($system);
        $supplier->requirements()->attach($requirement);
        $supplier->controls()->attach($control);
        SupplierAssessment::create([
            'supplier_id' => $supplier->id,
            'management_system_id' => $system->id,
            'assessed_at' => today(),
            'next_review_at' => today()->addDays(7),
            'risk_rating' => 'high',
            'status' => 'conditional',
            'required_actions' => 'Close encryption assurance gap.',
        ]);

        app(ActionItemSyncService::class)->sync();

        $this->assertDatabaseHas('action_items', ['category' => 'training_assignment', 'owner_id' => $user->id, 'status' => 'open']);
        $this->assertDatabaseHas('action_items', ['category' => 'competency_renewal', 'owner_id' => $user->id, 'status' => 'open']);
        $this->assertDatabaseHas('action_items', ['category' => 'supplier_review', 'owner_id' => $user->id, 'status' => 'open']);
        $this->assertDatabaseHas('action_items', ['category' => 'supplier_assessment', 'owner_id' => $user->id, 'status' => 'open']);
        $this->assertDatabaseHas('action_items', ['category' => 'supplier_treatment', 'owner_id' => $user->id, 'status' => 'open']);

        $snapshot = app(AssuranceAnalysisService::class)->refresh($system);
        $this->assertSame(1, $snapshot->attention_counts['overdue_mandatory_training']);
        $this->assertSame(1, $snapshot->attention_counts['competence_due']);
        $this->assertSame(1, $snapshot->attention_counts['high_risk_suppliers']);
        $this->assertSame(1, $snapshot->attention_counts['supplier_reviews_due']);

        $matrix = app(TraceabilityMatrixService::class)->build($system);
        $row = $matrix['rows']->first();
        $this->assertSame(1, $row['training_programs']);
        $this->assertSame(1, $row['competence_records']);
        $this->assertSame(1, $row['suppliers']);
        $this->assertSame(1, $row['high_risk_suppliers']);
        $this->assertSame('attention', $row['attention_state']);
    }

    public function test_auditor_pack_includes_people_and_supplier_registers(): void
    {
        [$system] = $this->createSystem();
        $path = app(AuditPackService::class)->generate($system);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);

        foreach (['training-register.csv','competence-register.csv','supplier-register.csv','supplier-assessments.csv'] as $file) {
            $this->assertNotFalse($zip->locateName($file), "Missing {$file}");
        }

        $zip->close();
        File::delete($path);
    }

    private function createSystem(): array
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001','name' => 'ISMS','edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG','name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id,'compliance_scope_id' => $scope->id,'code' => 'ISMS','name' => 'Information Security Management System']);
        $requirement = Requirement::create(['standard_id' => $standard->id,'code' => '7.2','title' => 'Competence']);
        $control = Control::create(['standard_id' => $standard->id,'code' => 'A.5.19','title' => 'Supplier relationships']);
        $requirement->controls()->attach($control);
        RequirementAssessment::create(['management_system_id' => $system->id,'requirement_id' => $requirement->id,'status' => 'conformant']);
        StatementOfApplicability::create(['management_system_id' => $system->id,'control_id' => $control->id,'applicable' => true,'implementation_status' => 'implemented']);
        return [$system, $requirement, $control];
    }
}
