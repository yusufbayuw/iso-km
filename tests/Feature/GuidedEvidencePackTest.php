<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\GuidedAssessment;
use App\Models\GuidedConsolidation;
use App\Models\GuidedDecision;
use App\Models\GuidedEvidenceAcceptance;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\ManagementSystem;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedEvidencePackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class GuidedEvidencePackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('iso-km.guided_assessment_enabled', true);
    }

    public function test_verified_evidence_pack_excludes_sensitive_files_unless_explicitly_authorized(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');
        $user = User::create(['name' => 'Coordinator', 'email' => 'evidencepack@example.test', 'password' => 'password']);
        Permission::findOrCreate('guided.report.export', 'web');
        Permission::findOrCreate('guided.evidence_pack.export_sensitive', 'web');
        $user->givePermissionTo('guided.report.export');
        $standard = Standard::create(['code' => 'GENERIC', 'name' => 'Standard', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'SCOPE', 'name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'SYS', 'name' => 'System']);
        $control = Control::create(['standard_id' => $standard->id, 'code' => 'A.1', 'title' => 'Control']);
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'B', 'name' => 'Bank']);
        $question = GuidedQuestion::create(['guided_question_bank_id' => $bank->id, 'code' => 'Q', 'prompt' => 'Proof?']);
        $target = GuidedQuestionTarget::create(['guided_question_id' => $question->id, 'control_id' => $control->id, 'criterion' => 'Proof']);
        $bank->update(['status' => 'published']);
        $run = GuidedAssessment::create(['management_system_id' => $system->id,
            'guided_question_bank_id' => $bank->id, 'name' => 'Run', 'created_by' => $user->id,
            'status' => 'locked', 'locked_at' => now()]);
        $consolidation = GuidedConsolidation::create(['guided_assessment_id' => $run->id,
            'guided_question_id' => $question->id, 'summary' => 'Evidence collected',
            'prepared_by' => $user->id, 'context_key' => 'general']);
        $decision = GuidedDecision::create(['guided_consolidation_id' => $consolidation->id,
            'guided_question_target_id' => $target->id, 'proposed_status' => 'implemented',
            'rationale' => 'Verified by assessor', 'risk_score' => 2,
            'status' => 'approved', 'approved_at' => now(), 'synced_at' => now()]);
        $evidences = [];
        foreach (['internal', 'restricted'] as $classification) {
            $file = 'iso/evidence/'.$classification.'.txt';
            Storage::disk('local')->put($file, 'Content-'.$classification);
            $evidence = Evidence::create(['management_system_id' => $system->id,
                'title' => $classification, 'owner_id' => $user->id,
                'file_path' => $file, 'confidentiality' => $classification,
                'status' => 'active', 'verification_status' => 'verified', 'verified_at' => now()]);
            GuidedEvidenceAcceptance::create(['guided_decision_id' => $decision->id,
                'evidence_id' => $evidence->id, 'status' => 'accepted', 'rationale' => 'Sufficient']);
            $evidences[] = $evidence;
        }

        $service = app(GuidedEvidencePackService::class);
        $safePath = $service->export($run, $user);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($safePath)) === true);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertCount(1, $manifest['included']);
        $this->assertCount(1, $manifest['omitted']);
        $this->assertNotFalse($zip->getFromName('evidence/EVD-'.$evidences[0]->id.'.txt'));
        $this->assertFalse($zip->getFromName('evidence/EVD-'.$evidences[1]->id.'.txt'));
        $zip->close();

        try {
            $service->export($run, $user, true);
            $this->fail('Sensitive export must require its own permission.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('authorization', $e->errors());
        }

        $user->givePermissionTo('guided.evidence_pack.export_sensitive');
        $sensitivePath = $service->export($run, $user, true);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($sensitivePath)) === true);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertCount(2, $manifest['included']);
        $zip->close();
        $this->assertDatabaseCount('guided_evidence_pack_records', 2);
    }
}
