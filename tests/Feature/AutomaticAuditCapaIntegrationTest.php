<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\ComplianceScope;
use App\Models\CorrectiveAction;
use App\Models\ManagementSystem;
use App\Models\Standard;
use App\Models\User;
use App\Services\CorrectiveActionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AutomaticAuditCapaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function audit(): Audit
    {
        $standard = Standard::create(['code' => 'ISMS', 'name' => 'Example Standard']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'SYS', 'name' => 'System']);

        return Audit::create(['management_system_id' => $system->id, 'code' => 'AUD-01', 'title' => 'Audit Internal']);
    }

    public function test_audit_completion_waits_for_reviewed_checklist_without_extra_fields(): void
    {
        $audit = $this->audit();
        $item = AuditChecklistItem::create([
            'audit_id' => $audit->id, 'question' => 'Review compliance evidence',
            'result' => 'not_reviewed',
        ]);
        $this->assertRejected(fn () => $audit->update(['status' => 'completed']));
        $item->update(['result' => 'conforming']);
        $audit->refresh()->update(['status' => 'completed']);
        $this->assertSame('completed', $audit->fresh()->status);
        $this->assertNotNull($audit->fresh()->actual_end_at);
    }

    public function test_checklist_creates_one_finding_without_extra_user_action(): void
    {
        $audit = $this->audit();
        $checklist = AuditChecklistItem::create([
            'audit_id' => $audit->id,
            'question' => 'Apakah akses pengguna direview secara berkala?',
            'result' => 'nonconformity',
            'notes' => 'Tidak ditemukan bukti review berkala.',
        ]);

        $finding = AuditFinding::query()->where('audit_checklist_item_id', $checklist->id)->firstOrFail();
        $this->assertSame($audit->id, $finding->audit_id);
        $this->assertSame('nonconformity', $finding->finding_type);
        $this->assertSame('open', $finding->status);

        $checklist->update(['notes' => 'Informasi tambahan dari auditor.']);
        $this->assertDatabaseCount('audit_findings', 1);
        $this->assertSame('Tidak ditemukan bukti review berkala.', $finding->fresh()->description);
    }

    public function test_capa_requires_independent_verification_and_closes_finding_automatically(): void
    {
        $audit = $this->audit();
        $finding = AuditFinding::create([
            'audit_id' => $audit->id,
            'code' => 'NCR-01',
            'title' => 'Tidak ada review berkala',
            'description' => 'Review akses belum dilakukan.',
            'root_cause' => 'Belum ditetapkan jadwal dan PIC review.',
        ]);
        $owner = User::create(['name' => 'Executor', 'email' => 'executor@example.test', 'password' => 'password']);
        $verifier = User::create(['name' => 'Verifier', 'email' => 'verifier@example.test', 'password' => 'password']);
        Permission::findOrCreate('capa.verify', 'web');
        $owner->givePermissionTo('capa.verify');
        $verifier->givePermissionTo('capa.verify');

        $action = CorrectiveAction::create([
            'audit_finding_id' => $finding->id,
            'action' => 'Tetapkan dan lakukan review akses.',
            'owner_id' => $owner->id,
            'status' => 'open',
        ]);
        $this->assertSame('action', $finding->refresh()->status);

        $this->assertRejected(fn () => $finding->update(['status' => 'closed']));
        $this->assertRejected(fn () => $action->update(['status' => 'completed']));

        $service = app(CorrectiveActionWorkflowService::class);
        $service->submitForVerification($action->fresh(), $owner);
        $this->assertSame('verification', $action->fresh()->status);
        $this->assertNotNull($action->fresh()->completed_at);
        $this->assertSame('verification', $finding->fresh()->status);

        $this->assertRejected(fn () => $service->verify($action->fresh(), $owner, true, 'Verifikasi sendiri'));
        $service->verify($action->fresh(), $verifier, false, 'Bukti belum lengkap.');
        $this->assertSame('in_progress', $action->fresh()->status);
        $this->assertSame('action', $finding->fresh()->status);

        $service->submitForVerification($action->fresh(), $owner);
        $service->verify($action->fresh(), $verifier, true, 'Evidence dan proses sudah efektif.');
        $this->assertSame('completed', $action->fresh()->status);
        $this->assertSame('effective', $action->fresh()->effectiveness_status);
        $this->assertSame('closed', $finding->fresh()->status);

        $this->assertRejected(fn () => $action->fresh()->update(['action' => 'Ubah bukti setelah disetujui']));
    }

    public function test_finding_waits_for_root_cause_and_all_capa_items(): void
    {
        $audit = $this->audit();
        $finding = AuditFinding::create([
            'audit_id' => $audit->id, 'code' => 'NCR-02',
            'title' => 'Perbaikan', 'description' => 'Ketidaksesuaian.',
        ]);
        $executor = User::create(['name' => 'Executor', 'email' => 'exec2@example.test', 'password' => 'password']);
        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'review2@example.test', 'password' => 'password']);
        Permission::findOrCreate('capa.verify', 'web');
        $reviewer->givePermissionTo('capa.verify');

        $a = CorrectiveAction::create(['audit_finding_id' => $finding->id, 'action' => 'A', 'owner_id' => $executor->id]);
        $b = CorrectiveAction::create(['audit_finding_id' => $finding->id, 'action' => 'B', 'owner_id' => $executor->id]);
        $service = app(CorrectiveActionWorkflowService::class);
        foreach ([$a, $b] as $action) {
            $service->submitForVerification($action->fresh(), $executor);
            $service->verify($action->fresh(), $reviewer, true, 'Efektif');
        }

        $this->assertSame('verification', $finding->fresh()->status);
        $finding->update(['root_cause' => 'Proses kontrol sebelumnya tidak terdokumentasi.']);
        $this->assertNotNull($finding->fresh()->root_cause);
        $this->assertSame(2, $finding->fresh()->correctiveActions()->where('status', 'completed')->count());
        $this->assertSame('closed', $finding->fresh()->status);
    }

    private function assertRejected(callable $attempt): void
    {
        try {
            $attempt();
            $this->fail('Operasi tidak sah harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
