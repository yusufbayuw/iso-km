<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\AssuranceSnapshot;
use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\EvidenceRequest;
use App\Models\EvidenceSchedule;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Standard;
use App\Models\StatementOfApplicability;
use App\Models\User;
use App\Services\ActionItemSyncService;
use App\Services\AssuranceAnalysisService;
use App\Services\EvidenceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComplianceIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_evidence_flows_into_work_queue_and_assurance(): void
    {
        [$system, $requirement, $control] = $this->createManagementSystem();
        $owner = User::create(['name' => 'Process Owner','email' => 'owner@example.test','password' => 'password']);
        $verifier = User::create(['name' => 'Verifier','email' => 'verifier@example.test','password' => 'password']);
        Permission::findOrCreate('evidence.verify', 'web'); $verifier->givePermissionTo('evidence.verify');
        $schedule = EvidenceSchedule::create(['management_system_id' => $system->id,'owner_id' => $owner->id,'code' => 'EVD-BACKUP-MONTHLY','title' => 'Backup restore test','evidence_type' => 'report','frequency' => 'monthly','lead_days' => 7,'validity_days' => 45,'next_due_at' => today()->addDays(3)]);
        $schedule->requirements()->attach($requirement); $schedule->controls()->attach($control);
        $lifecycle = app(EvidenceLifecycleService::class); $this->assertSame(1, $lifecycle->generateDueRequests());
        $request = EvidenceRequest::firstOrFail(); $this->assertSame('requested', $request->status); $this->assertSame($owner->id, $request->owner_id);
        app(ActionItemSyncService::class)->sync(); $work = ActionItem::where('category', 'evidence_collection')->firstOrFail(); $this->assertSame($owner->id, $work->owner_id); $this->assertSame('open', $work->status);
        $evidence = Evidence::create(['management_system_id' => $system->id,'owner_id' => $owner->id,'title' => 'Backup restore September','type' => 'report','status' => 'active','verification_status' => 'pending','captured_at' => today()]);
        $lifecycle->submit($request, $evidence, $owner); $request->refresh(); $this->assertSame('submitted', $request->status); $this->assertTrue($evidence->refresh()->controls->contains($control)); $this->assertTrue($evidence->requirements->contains($requirement));
        $lifecycle->verify($request, $verifier, 'Restore berhasil diverifikasi.'); $this->assertSame('verified', $request->refresh()->status); $this->assertSame('verified', $evidence->refresh()->verification_status); $this->assertSame($verifier->id, $evidence->verified_by);
        app(ActionItemSyncService::class)->sync(); $this->assertSame('completed', $work->refresh()->status);
        $snapshot = app(AssuranceAnalysisService::class)->refresh($system); $this->assertSame('100.00', $snapshot->requirement_readiness); $this->assertSame('100.00', $snapshot->control_implementation); $this->assertSame('100.00', $snapshot->evidence_coverage); $this->assertSame('100.00', $snapshot->evidence_freshness); $this->assertSame('high', $snapshot->confidence_level);
    }

    public function test_intelligence_refresh_command_is_idempotent_for_due_requests(): void
    {
        [$system, $requirement, $control] = $this->createManagementSystem();
        $owner = User::create(['name' => 'Owner','email' => 'owner2@example.test','password' => 'password']);
        $schedule = EvidenceSchedule::create(['management_system_id' => $system->id,'owner_id' => $owner->id,'code' => 'EVD-QUARTERLY','title' => 'Quarterly access review','evidence_type' => 'report','frequency' => 'quarterly','lead_days' => 30,'next_due_at' => today()->addDays(7)]);
        $schedule->requirements()->attach($requirement); $schedule->controls()->attach($control);
        $this->artisan('iso:refresh-intelligence')->assertExitCode(0); $this->assertDatabaseCount('evidence_requests', 1); $this->assertDatabaseCount('action_items', 1); $this->assertDatabaseCount('assurance_snapshots', 1);
        $this->artisan('iso:refresh-intelligence')->assertExitCode(0); $this->assertDatabaseCount('evidence_requests', 1); $this->assertDatabaseCount('action_items', 1); $this->assertDatabaseCount('assurance_snapshots', 1); $this->assertInstanceOf(AssuranceSnapshot::class, $system->refresh()->assuranceSnapshot);
    }

    private function createManagementSystem(): array
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001','name' => 'ISMS','edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG','name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id,'compliance_scope_id' => $scope->id,'code' => 'ISMS','name' => 'ISMS']);
        $requirement = Requirement::create(['standard_id' => $standard->id,'code' => '8.1','title' => 'Operational planning']);
        $control = Control::create(['standard_id' => $standard->id,'code' => 'A.8.1','title' => 'Example control']);
        RequirementAssessment::create(['management_system_id' => $system->id,'requirement_id' => $requirement->id,'status' => 'conformant']);
        StatementOfApplicability::create(['management_system_id' => $system->id,'control_id' => $control->id,'applicable' => true,'implementation_status' => 'implemented']);
        return [$system, $requirement, $control];
    }
}
