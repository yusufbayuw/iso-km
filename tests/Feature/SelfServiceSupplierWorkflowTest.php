<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\Document;
use App\Models\DocumentDistribution;
use App\Models\DocumentRevision;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\Supplier;
use App\Models\SupplierAssessment;
use App\Models\TrainingAssignment;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ActionItemSyncService;
use App\Services\DocumentWorkflowService;
use App\Services\SupplierAssessmentWorkflowService;
use App\Services\TrainingAssignmentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SelfServiceSupplierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_self_service_flows_to_training_verification_and_document_acknowledgement(): void
    {
        [$system, $requirement, $control] = $this->createSystem();
        $employee = User::factory()->create();
        $coordinator = User::factory()->create();
        $this->grant($employee, ['training.complete', 'document.acknowledge']);
        $this->grant($coordinator, ['training.verify']);

        $program = TrainingProgram::create([
            'management_system_id' => $system->id,
            'code' => 'TRN-SELF',
            'title' => 'Security Awareness',
            'owner_id' => $coordinator->id,
            'mandatory' => true,
            'validity_months' => 12,
            'status' => 'active',
        ]);
        $program->requirements()->attach($requirement);
        $program->controls()->attach($control);

        $assignment = TrainingAssignment::create([
            'training_program_id' => $program->id,
            'user_id' => $employee->id,
            'cycle_key' => '2026-Q3',
            'assigned_at' => today(),
            'due_at' => today()->addWeek(),
            'status' => 'assigned',
            'result' => 'pending',
        ]);

        app(ActionItemSyncService::class)->sync();
        $this->assertDatabaseHas('action_items', ['category' => 'training_assignment', 'owner_id' => $employee->id, 'status' => 'open']);

        app(TrainingAssignmentWorkflowService::class)->start($assignment, $employee);
        $evidence = Evidence::create([
            'management_system_id' => $system->id,
            'title' => 'Training proof',
            'type' => 'training_record',
            'source' => 'employee_self_service',
            'confidentiality' => 'internal',
            'owner_id' => $employee->id,
            'captured_at' => today(),
            'status' => 'active',
            'verification_status' => 'pending',
        ]);
        $evidence->requirements()->attach($requirement);
        $evidence->controls()->attach($control);

        app(TrainingAssignmentWorkflowService::class)->submit($assignment->refresh(), $employee, $evidence, 'Completed awareness module.');
        app(ActionItemSyncService::class)->sync();

        $this->assertDatabaseHas('training_assignments', ['id' => $assignment->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('action_items', ['category' => 'training_assignment', 'owner_id' => $employee->id, 'status' => 'completed']);
        $this->assertDatabaseHas('action_items', ['category' => 'training_verification', 'owner_id' => $coordinator->id, 'status' => 'open']);

        app(TrainingAssignmentWorkflowService::class)->verify($assignment->refresh(), $coordinator, 'pass', 90, 'Verified.');
        app(ActionItemSyncService::class)->sync();

        $this->assertDatabaseHas('training_assignments', ['id' => $assignment->id, 'status' => 'completed', 'result' => 'pass', 'verified_by' => $coordinator->id]);
        $this->assertDatabaseHas('evidences', ['id' => $evidence->id, 'verification_status' => 'verified', 'verified_by' => $coordinator->id]);
        $this->assertDatabaseHas('action_items', ['category' => 'training_verification', 'owner_id' => $coordinator->id, 'status' => 'completed']);

        $this->actingAs($employee)->get('/portal/my-training')->assertOk();

        Storage::disk(config('filesystems.default'))->put('iso/documents/self-service.txt', 'controlled copy');
        $document = Document::create([
            'code' => 'DOC-SELF',
            'title' => 'Self Service Policy',
            'type' => 'policy',
            'confidentiality' => 'internal',
            'status' => 'effective',
        ]);
        $document->managementSystems()->attach($system);
        $revision = DocumentRevision::create([
            'document_id' => $document->id,
            'revision' => '1.0',
            'file_path' => 'iso/documents/self-service.txt',
            'status' => 'effective',
            'effective_at' => today(),
            'is_current' => true,
        ]);
        $distribution = DocumentDistribution::create([
            'document_id' => $document->id,
            'user_id' => $employee->id,
            'document_revision_id' => $revision->id,
            'distributed_at' => now(),
            'acknowledgement_due_at' => today()->addWeek(),
            'status' => 'pending',
        ]);

        $this->actingAs($employee)->get(route('controlled-documents.download', $distribution))->assertOk();
        app(DocumentWorkflowService::class)->acknowledge($distribution, $employee);
        $this->assertDatabaseHas('document_distributions', ['id' => $distribution->id, 'status' => 'acknowledged']);
        $this->actingAs($employee)->get('/portal/my-documents')->assertOk();
    }

    public function test_supplier_assessment_uses_controlled_state_transitions(): void
    {
        [$system] = $this->createSystem();
        $executor = User::factory()->create();
        $reviewer = User::factory()->create();
        $decider = User::factory()->create();
        $verifier = User::factory()->create();
        $this->grant($executor, ['supplier.assess']);
        $this->grant($reviewer, ['supplier.review']);
        $this->grant($decider, ['supplier.decide']);
        $this->grant($verifier, ['supplier.verify_treatment']);

        $supplier = Supplier::create([
            'code' => 'SUP-WF',
            'name' => 'Workflow Supplier',
            'owner_id' => $executor->id,
            'criticality' => 'high',
            'status' => 'active',
        ]);
        $supplier->managementSystems()->attach($system);
        $assessment = SupplierAssessment::create([
            'supplier_id' => $supplier->id,
            'management_system_id' => $system->id,
            'status' => 'planned',
            'risk_rating' => 'medium',
        ]);

        $workflow = app(SupplierAssessmentWorkflowService::class);
        $workflow->request($assessment, $executor);
        $workflow->start($assessment->refresh(), $executor);
        $workflow->submit($assessment->refresh(), $executor, 'Assessment completed.');
        $workflow->beginReview($assessment->refresh(), $reviewer, 'Review complete.');
        $workflow->decide($assessment->refresh(), $decider, 'conditional', 'high', 65, 'Close encryption assurance gap.', today()->addMonth()->toDateString());
        app(ActionItemSyncService::class)->sync();

        $this->assertDatabaseHas('supplier_assessments', ['id' => $assessment->id, 'status' => 'conditional', 'risk_rating' => 'high', 'decided_by' => $decider->id]);
        $this->assertDatabaseHas('action_items', ['category' => 'supplier_treatment', 'owner_id' => $executor->id, 'status' => 'open']);

        $workflow->verifyTreatment($assessment->refresh(), $verifier, 'Encryption assurance evidence validated.');
        app(ActionItemSyncService::class)->sync();
        $this->assertDatabaseHas('supplier_assessments', ['id' => $assessment->id, 'status' => 'accepted', 'treatment_verified_by' => $verifier->id]);
        $this->assertDatabaseHas('action_items', ['category' => 'supplier_treatment', 'owner_id' => $executor->id, 'status' => 'completed']);

        $invalid = SupplierAssessment::create([
            'supplier_id' => $supplier->id,
            'management_system_id' => $system->id,
            'status' => 'planned',
            'risk_rating' => 'medium',
        ]);

        $this->expectException(ValidationException::class);
        $workflow->decide($invalid, $decider, 'accepted', 'low');
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }
    }

    private function createSystem(): array
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001','name' => 'ISMS','edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG','name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id,'compliance_scope_id' => $scope->id,'code' => 'ISMS','name' => 'Information Security Management System']);
        $requirement = Requirement::create(['standard_id' => $standard->id,'code' => '7.2','title' => 'Competence']);
        $control = Control::create(['standard_id' => $standard->id,'code' => 'A.5.19','title' => 'Supplier relationships']);
        $requirement->controls()->attach($control);
        return [$system, $requirement, $control];
    }
}
