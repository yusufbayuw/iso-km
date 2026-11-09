<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\GuidedAssessment;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\StatementOfApplicability;
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use App\Services\GuidedQuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedAssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $label, array $abilities): User
    {
        $user = User::create([
            'name' => $label, 'email' => strtolower($label).'@example.test', 'password' => 'password',
        ]);
        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $user->givePermissionTo($abilities);
        return $user;
    }

    private function setupAssessment(): array
    {
        $standard = Standard::create(['code' => 'ISMS-EXAMPLE', 'name' => 'Example Management Standard', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'SCOPE', 'name' => 'Organization Scope']);
        $system = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'SYS', 'name' => 'Management System',
        ]);
        $requirement = Requirement::create(['standard_id' => $standard->id, 'code' => '8.1', 'title' => 'Operational control']);
        $control = Control::create(['standard_id' => $standard->id, 'code' => 'A.8.13', 'title' => 'Backup']);
        $coordinator = $this->actor('Coordinator', ['guided.bank.manage', 'guided.assessment.manage', 'guided.consolidate', 'guided.assessment.view']);
        $bank = GuidedQuestionBank::create([
            'standard_id' => $standard->id, 'code' => 'BASE', 'name' => 'Baseline', 'version' => 1,
            'created_by' => $coordinator->id,
        ]);
        $question = GuidedQuestion::create([
            'guided_question_bank_id' => $bank->id, 'code' => 'Q-001',
            'prompt' => 'Apakah backup berjalan?', 'answer_type' => 'single_choice',
            'answer_options' => ['yes', 'partial', 'no'],
        ]);
        $requirementTarget = GuidedQuestionTarget::create([
            'guided_question_id' => $question->id, 'requirement_id' => $requirement->id,
            'criterion' => 'Pencadangan operasional', 'evidence_profile' => 'standard',
        ]);
        $controlTarget = GuidedQuestionTarget::create([
            'guided_question_id' => $question->id, 'control_id' => $control->id,
            'criterion' => 'Pelaksanaan backup', 'evidence_profile' => 'critical',
        ]);
        app(GuidedQuestionBankService::class)->publish($bank, $coordinator);
        $run = GuidedAssessment::create([
            'management_system_id' => $system->id, 'guided_question_bank_id' => $bank->id,
            'name' => 'Assessment Example', 'created_by' => $coordinator->id,
            'high_risk_threshold' => 12, 'critical_risk_threshold' => 20,
        ]);
        return compact('system', 'requirement', 'control', 'coordinator', 'bank', 'question', 'requirementTarget', 'controlTarget', 'run');
    }

    public function test_multiple_answers_are_preserved_and_risk_approvals_publish_only_after_all_stages(): void
    {
        extract($this->setupAssessment());
        $service = app(GuidedAssessmentWorkflowService::class);
        $respondent = $this->actor('Respondent', ['guided.answer.submit', 'view_any_evidence']);
        $reviewer = $this->actor('Reviewer', ['guided.evidence.review']);
        $assessor = $this->actor('Assessor', ['guided.assessment.review']);
        $riskOwner = $this->actor('Riskowner', ['guided.risk.approve']);
        $management = $this->actor('Management', ['guided.management.approve']);
        foreach (['reviewer' => $reviewer, 'assessor' => $assessor, 'risk_owner' => $riskOwner, 'management' => $management] as $role => $user) {
            $run->memberships()->create(['user_id' => $user->id, 'role' => $role]);
        }
        $assignment = $service->assign($run, $question, $respondent, $coordinator);
        $evidence = Evidence::create([
            'management_system_id' => $system->id, 'title' => 'Verified restore test',
            'owner_id' => $respondent->id, 'status' => 'active',
            'verification_status' => 'verified', 'captured_at' => today(),
        ]);
        $first = $service->submitAnswer($assignment, $respondent, ['value' => 'partial'], 'database', 'Backup sebagian', [$evidence->id]);
        $second = $service->submitAnswer($assignment, $respondent, ['value' => 'yes'], 'database', 'Informasi lanjutan', [$evidence->id], $first->id);
        $this->assertDatabaseCount('guided_answers', 2);
        $this->assertSame($first->id, $second->supersedes_id);
        $consolidation = $service->consolidate($run, $question, $coordinator, [$first->id, $second->id], 'Backup data diverifikasi.', 'merge', 'database');
        $this->assertCount(2, $consolidation->sources);

        $controlDecision = $service->propose($consolidation, $controlTarget, $coordinator, 'implemented', 20, 'Bukti operasional diperiksa.', $riskOwner->id, true);
        $service->acceptEvidence($controlDecision, $evidence, $reviewer, true, 'Relevan dan memadai untuk kriteria.');
        $service->approve($controlDecision, $assessor, 'Kriteria terpenuhi.');
        $this->assertSame('in_review', $controlDecision->refresh()->status);
        $service->approve($controlDecision, $riskOwner, 'Risiko telah ditinjau.');
        $this->assertSame('in_review', $controlDecision->refresh()->status);
        $service->approve($controlDecision, $management, 'Disetujui sesuai kewenangan.');
        $this->assertSame('approved', $controlDecision->refresh()->status);
        $this->assertDatabaseMissing('soa_entries', ['management_system_id' => $system->id, 'control_id' => $control->id]);
        $service->publish($controlDecision, $coordinator);
        $this->assertSame('implemented', StatementOfApplicability::query()->firstOrFail()->implementation_status);

        $requirementDecision = $service->propose($consolidation, $requirementTarget, $coordinator, 'conformant', 3, 'Kontrol yang diperlukan berjalan.');
        $service->acceptEvidence($requirementDecision, $evidence, $reviewer, true, 'Mendukung penilaian requirement.');
        $service->approve($requirementDecision, $assessor, 'Hasil dapat diterima.');
        $service->publish($requirementDecision, $coordinator);
        $service->lock($run, $coordinator);
        $this->assertSame('locked', $run->refresh()->status);
        $this->assertDatabaseCount('guided_answers', 2);
        $this->assertDatabaseCount('guided_approvals', 4);
    }

    public function test_unverified_evidence_and_self_approval_are_rejected(): void
    {
        extract($this->setupAssessment());
        $service = app(GuidedAssessmentWorkflowService::class);
        $respondent = $this->actor('Submitter', ['guided.answer.submit', 'view_any_evidence', 'guided.assessment.review']);
        $run->memberships()->create(['user_id' => $respondent->id, 'role' => 'assessor']);
        $reviewer = $this->actor('Verifier', ['guided.evidence.review']);
        $run->memberships()->create(['user_id' => $reviewer->id, 'role' => 'reviewer']);
        $assignment = $service->assign($run, $question, $respondent, $coordinator);
        $evidence = Evidence::create([
            'management_system_id' => $system->id, 'owner_id' => $respondent->id, 'title' => 'Pending evidence',
            'status' => 'active', 'verification_status' => 'pending',
        ]);
        $answer = $service->submitAnswer($assignment, $respondent, ['value' => 'yes'], 'general', null, [$evidence->id]);
        $consolidation = $service->consolidate($run, $question, $coordinator, [$answer->id], 'Jawaban responden', 'select');
        $decision = $service->propose($consolidation, $controlTarget, $coordinator, 'implemented', 2, 'Perlu pembuktian.');
        try {
            $service->acceptEvidence($decision, $evidence, $reviewer, true, 'Perlu verifikasi');
            $this->fail('Evidence pending harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evidence', $exception->errors());
        }
        try {
            $service->approve($decision, $respondent, 'Mencoba menyetujui hasil sendiri.');
            $this->fail('Self approval harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('authorization', $exception->errors());
        }
    }

    public function test_lock_requires_all_mapped_targets(): void
    {
        extract($this->setupAssessment());
        $service = app(GuidedAssessmentWorkflowService::class);
        $respondent = $this->actor('Writer', ['guided.answer.submit']);
        $assignment = $service->assign($run, $question, $respondent, $coordinator);
        $service->submitAnswer($assignment, $respondent, ['value' => 'no']);
        $this->expectException(ValidationException::class);
        $service->lock($run, $coordinator);
    }
}
