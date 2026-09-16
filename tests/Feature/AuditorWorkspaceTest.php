<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\Standard;
use App\Models\StatementOfApplicability;
use App\Services\AuditPackService;
use App\Services\TraceabilityMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class AuditorWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_traceability_matrix_connects_requirement_to_assurance_evidence_and_attention(): void
    {
        [$system, $requirement, $control] = $this->createSystem();

        $document = Document::create(['code' => 'SOP-001','title' => 'Operational SOP','status' => 'effective']);
        $document->managementSystems()->attach($system);
        $document->requirements()->attach($requirement);
        $document->controls()->attach($control);

        $evidence = Evidence::create([
            'management_system_id' => $system->id,
            'title' => 'September control evidence',
            'type' => 'report',
            'status' => 'active',
            'verification_status' => 'verified',
            'captured_at' => today(),
            'expires_at' => today()->addMonth(),
        ]);
        $evidence->requirements()->attach($requirement);
        $evidence->controls()->attach($control);

        $risk = Risk::create([
            'management_system_id' => $system->id,
            'code' => 'R-001',
            'title' => 'High operational risk',
            'likelihood' => 4,
            'impact' => 4,
            'residual_likelihood' => 4,
            'residual_impact' => 4,
            'status' => 'open',
        ]);
        $risk->requirements()->attach($requirement);
        $risk->controls()->attach($control);

        $audit = Audit::create(['management_system_id' => $system->id,'code' => 'AUD-001','title' => 'Internal Audit','type' => 'internal','status' => 'completed']);
        $finding = AuditFinding::create(['audit_id' => $audit->id,'code' => 'F-001','finding_type' => 'nonconformity','severity' => 'major','title' => 'Open finding','requirement_id' => $requirement->id,'control_id' => $control->id,'status' => 'open']);
        CorrectiveAction::create(['audit_finding_id' => $finding->id,'action' => 'Fix the process','status' => 'open']);

        $matrix = app(TraceabilityMatrixService::class)->build($system);
        $row = $matrix['rows']->first();

        $this->assertSame(1, $matrix['summary']['requirements']);
        $this->assertSame(1, $row['documents']);
        $this->assertSame(1, $row['effective_documents']);
        $this->assertSame(1, $row['verified_fresh_evidence']);
        $this->assertSame(1, $row['high_risks']);
        $this->assertSame(1, $row['open_findings']);
        $this->assertSame(1, $row['open_capa']);
        $this->assertSame('attention', $row['attention_state']);
    }

    public function test_auditor_pack_contains_structured_source_of_truth_exports(): void
    {
        [$system, $requirement, $control] = $this->createSystem();
        $evidence = Evidence::create(['management_system_id' => $system->id,'title' => 'Evidence','type' => 'record','status' => 'active','verification_status' => 'verified','captured_at' => today()]);
        $evidence->requirements()->attach($requirement);
        $evidence->controls()->attach($control);

        $path = app(AuditPackService::class)->generate($system);

        $this->assertFileExists($path);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);

        foreach (['manifest.json','traceability-matrix.csv','statement-of-applicability.csv','risk-register.csv','evidence-register.csv','audit-register.csv','findings-capa.csv','management-review.csv'] as $file) {
            $this->assertNotFalse($zip->locateName($file), "Missing {$file}");
        }

        $this->assertStringContainsString('8.1', $zip->getFromName('traceability-matrix.csv'));
        $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ISMS', $manifest['management_system']['code']);
        $this->assertArrayHasKey('disclaimer', $manifest);

        $zip->close();
        File::delete($path);
    }

    private function createSystem(): array
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001','name' => 'ISMS','edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG','name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id,'compliance_scope_id' => $scope->id,'code' => 'ISMS','name' => 'Information Security Management System']);
        $requirement = Requirement::create(['standard_id' => $standard->id,'code' => '8.1','title' => 'Operational planning']);
        $control = Control::create(['standard_id' => $standard->id,'code' => 'A.8.1','title' => 'Example control']);
        $requirement->controls()->attach($control);
        RequirementAssessment::create(['management_system_id' => $system->id,'requirement_id' => $requirement->id,'status' => 'conformant']);
        StatementOfApplicability::create(['management_system_id' => $system->id,'control_id' => $control->id,'applicable' => true,'implementation_status' => 'implemented']);

        return [$system, $requirement, $control];
    }
}
