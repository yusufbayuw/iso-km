<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Evidence;
use App\Models\ExternalAuditAccess;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\RequirementMapping;
use App\Models\Standard;
use App\Models\User;
use App\Services\CrossStandardCoverageService;
use App\Services\CrossStandardMappingService;
use App\Services\ExternalAuditPackService;
use App\Services\TraceabilityMatrixService;
use Database\Seeders\IsoRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class ExternalAuditCrossStandardTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_auditor_only_sees_granted_management_system_and_can_export_sanitized_pack(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);
        [$source, $target] = $this->createSystems();
        $auditor = User::factory()->create();
        $auditor->assignRole('external_auditor');

        ExternalAuditAccess::create([
            'user_id' => $auditor->id,
            'management_system_id' => $target->id,
            'valid_from' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'allow_audit_pack' => true,
            'status' => 'active',
        ]);

        $this->actingAs($auditor)
            ->get('/portal/external-auditor-workspace')
            ->assertOk()
            ->assertSee($target->code)
            ->assertDontSee($source->code);

        $this->actingAs($auditor)->get('/portal/auditor-workspace')->assertForbidden();

        $path = app(ExternalAuditPackService::class)->generate($target);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        foreach (['manifest.json','traceability-matrix.csv','evidence-index.csv','cross-standard-evidence-reuse.csv','findings-summary.csv'] as $file) {
            $this->assertNotFalse($zip->locateName($file), "Missing {$file}");
        }
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertFalse($manifest['raw_files_included']);
        $zip->close();
        File::delete($path);
    }

    public function test_approved_cross_standard_mapping_can_reuse_verified_evidence_without_copying_source_record(): void
    {
        $this->seed(IsoRolePermissionSeeder::class);
        [$source, $target, $sourceRequirement, $targetRequirement] = $this->createSystems(withRequirements: true);
        $manager = User::factory()->create();
        $manager->assignRole('iso_manager');
        $this->actingAs($manager);

        $evidence = Evidence::create([
            'management_system_id' => $source->id,
            'title' => 'Shared governance evidence',
            'type' => 'record',
            'source' => 'internal',
            'confidentiality' => 'internal',
            'captured_at' => today(),
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_by' => $manager->id,
            'verified_at' => now(),
        ]);
        $evidence->requirements()->attach($sourceRequirement);

        $mapping = RequirementMapping::create([
            'requirement_a_id' => $sourceRequirement->id,
            'requirement_b_id' => $targetRequirement->id,
            'mapping_type' => 'partial',
            'coverage_percent' => 80,
            'rationale' => 'Shared governance evidence can support both requirements after review.',
        ]);
        app(CrossStandardMappingService::class)->approveRequirement($mapping, $manager);

        $coverage = app(CrossStandardCoverageService::class)->build($target);
        $this->assertSame(1, $coverage['summary']['opportunities']);
        $candidate = $coverage['candidates']->first();
        $this->assertSame('available', $candidate['reuse_status']);

        app(CrossStandardMappingService::class)->acceptRequirementReuse($evidence, $target, $targetRequirement, $mapping->refresh(), $manager);

        $this->assertDatabaseHas('evidence_reuses', [
            'evidence_id' => $evidence->id,
            'target_management_system_id' => $target->id,
            'target_requirement_id' => $targetRequirement->id,
            'status' => 'accepted',
        ]);
        $this->assertSame($source->id, $evidence->refresh()->management_system_id);

        $matrix = app(TraceabilityMatrixService::class)->build($target);
        $row = $matrix['rows']->first();
        $this->assertSame(0, $row['verified_fresh_evidence']);
        $this->assertSame(1, $row['verified_fresh_reused_evidence']);
        $this->assertSame(1, $row['verified_fresh_evidence_total']);
        $this->assertSame('supported', $row['attention_state']);

        $this->actingAs($manager)->get('/portal/cross-standard-workspace')->assertOk()->assertSee('Shared governance evidence');
    }

    public function test_mapping_within_same_standard_is_rejected(): void
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001','name' => 'ISMS','edition' => '2022']);
        $a = Requirement::create(['standard_id' => $standard->id,'code' => '4','title' => 'Context']);
        $b = Requirement::create(['standard_id' => $standard->id,'code' => '5','title' => 'Leadership']);

        $this->expectException(ValidationException::class);
        RequirementMapping::create(['requirement_a_id' => $a->id,'requirement_b_id' => $b->id]);
    }

    private function createSystems(bool $withRequirements = false): array
    {
        $scope = ComplianceScope::create(['code' => 'ORG','name' => 'Organization']);
        $standardA = Standard::create(['code' => 'ISO/IEC 27001','name' => 'ISMS','edition' => '2022']);
        $standardB = Standard::create(['code' => 'ISO 9001','name' => 'QMS','edition' => '2015']);
        $source = ManagementSystem::create(['standard_id' => $standardA->id,'compliance_scope_id' => $scope->id,'code' => 'ISMS','name' => 'Information Security','status' => 'active']);
        $target = ManagementSystem::create(['standard_id' => $standardB->id,'compliance_scope_id' => $scope->id,'code' => 'QMS','name' => 'Quality Management','status' => 'active']);

        if (! $withRequirements) { return [$source, $target]; }

        $sourceRequirement = Requirement::create(['standard_id' => $standardA->id,'code' => '7.5','title' => 'Documented information']);
        $targetRequirement = Requirement::create(['standard_id' => $standardB->id,'code' => '7.5','title' => 'Documented information']);
        RequirementAssessment::create(['management_system_id' => $target->id,'requirement_id' => $targetRequirement->id,'status' => 'conformant']);
        return [$source, $target, $sourceRequirement, $targetRequirement];
    }
}
