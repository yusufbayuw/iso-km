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
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use App\Services\GuidedEvidenceAccessService;
use App\Services\GuidedQuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedAssessmentHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $name, array $permissions = []): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'password']);
        foreach ($permissions as $p) { Permission::findOrCreate($p, 'web'); }
        $user->givePermissionTo($permissions);
        return $user;
    }

    private function fixture(): array
    {
        $standard = Standard::create(['code' => 'GENERIC', 'name' => 'Unbranded Standard', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'ORG1', 'name' => 'Generic Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'A', 'name' => 'Management System']);
        $coordinator = $this->actor('Coordinator', ['guided.bank.manage', 'guided.assessment.manage', 'guided.consolidate']);
        $respondent = $this->actor('Respondent', ['guided.answer.submit', 'view_any_evidence']);
        $requirement = Requirement::create(['standard_id' => $standard->id, 'code' => '1.1', 'title' => 'Requirement']);
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'BANK', 'name' => 'Questions']);
        $question = GuidedQuestion::create(['guided_question_bank_id' => $bank->id, 'code' => 'Q1',
            'prompt' => 'How is information protected?', 'answer_options' => ['yes', 'partial', 'no']]);
        $target = GuidedQuestionTarget::create(['guided_question_id' => $question->id,
            'requirement_id' => $requirement->id, 'criterion' => 'Condition evidenced']);
        app(GuidedQuestionBankService::class)->publish($bank, $coordinator);
        $run = GuidedAssessment::create(['management_system_id' => $system->id,
            'guided_question_bank_id' => $bank->id, 'name' => 'Assessment', 'created_by' => $coordinator->id]);
        $assignment = app(GuidedAssessmentWorkflowService::class)->assign($run, $question, $respondent, $coordinator);
        return compact('system', 'coordinator', 'respondent', 'requirement', 'bank', 'question', 'target', 'run', 'assignment');
    }

    public function test_incomplete_iso27001_2022_catalog_cannot_be_published_as_complete(): void
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        $actor = $this->actor('Editor', ['guided.bank.manage']);
        $r = Requirement::create(['standard_id' => $standard->id, 'code' => '4', 'title' => 'Clause heading']);
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'BASE', 'name' => 'Partial Bank']);
        $q = GuidedQuestion::create(['guided_question_bank_id' => $bank->id, 'code' => 'Q',
            'prompt' => 'Context?', 'answer_options' => ['yes', 'no']]);
        GuidedQuestionTarget::create(['guided_question_id' => $q->id, 'requirement_id' => $r->id, 'criterion' => 'Context established']);
        $coverage = app(GuidedQuestionBankService::class)->coverage($bank);
        $this->assertCount(93, $coverage['catalog_missing_controls']);
        $this->assertContains('4.1', $coverage['catalog_missing_requirements']);
        $this->expectException(ValidationException::class);
        app(GuidedQuestionBankService::class)->publish($bank, $actor);
    }

    public function test_respondent_cannot_attach_someone_elses_restricted_evidence_or_cross_scope_evidence(): void
    {
        extract($this->fixture());
        $owner = $this->actor('Owner');
        $internal = Evidence::create(['management_system_id' => $system->id, 'owner_id' => $owner->id,
            'title' => 'Shared internal', 'confidentiality' => 'internal', 'status' => 'active']);
        $private = Evidence::create(['management_system_id' => $system->id, 'owner_id' => $owner->id,
            'title' => 'Private payroll', 'confidentiality' => 'restricted', 'status' => 'active']);
        $owned = Evidence::create(['management_system_id' => $system->id, 'owner_id' => $respondent->id,
            'title' => 'Own confidential', 'confidentiality' => 'restricted', 'status' => 'active']);
        $otherScope = ComplianceScope::create(['code' => 'ORG2', 'name' => 'Separate Organization']);
        $other = ManagementSystem::create(['standard_id' => $system->standard_id,
            'compliance_scope_id' => $otherScope->id, 'code' => 'B', 'name' => 'Another Management System']);
        $foreign = Evidence::create(['management_system_id' => $other->id, 'owner_id' => $respondent->id,
            'title' => 'Out of scope', 'confidentiality' => 'public', 'status' => 'active']);

        $access = app(GuidedEvidenceAccessService::class);
        $allowed = $access->selectableQuery($assignment, $respondent)->pluck('id')->all();
        $this->assertContains($internal->id, $allowed);
        $this->assertContains($owned->id, $allowed);
        $this->assertNotContains($private->id, $allowed);
        $this->assertNotContains($foreign->id, $allowed);

        try {
            app(GuidedAssessmentWorkflowService::class)->submitAnswer($assignment, $respondent,
                ['value' => 'yes'], 'general', null, [$private->id]);
            $this->fail('A restricted record owned by someone else must not be attachable.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('evidence', $e->errors());
        }
        $answer = app(GuidedAssessmentWorkflowService::class)->submitAnswer($assignment, $respondent,
            ['value' => 'yes'], 'general', null, [$owned->id, $internal->id]);
        $this->assertCount(2, $answer->evidences);
    }

    public function test_human_can_consolidate_multiple_answers_from_different_contexts_and_later_answers_invalidate_old_result(): void
    {
        extract($this->fixture());
        $service = app(GuidedAssessmentWorkflowService::class);
        $db = $service->submitAnswer($assignment, $respondent, ['value' => 'yes'], 'database');
        $cloud = $service->submitAnswer($assignment, $respondent, ['value' => 'partial'], 'cloud');
        $consolidation = $service->consolidate($run, $question, $coordinator,
            [$db->id, $cloud->id], 'Database protected; cloud still partial.', 'merge', '*', 'Scope differences recorded.');
        $this->assertCount(2, $consolidation->sources);
        $this->assertSame('*', $consolidation->context_key);
        $this->assertTrue($consolidation->is_current);

        $service->submitAnswer($assignment, $respondent, ['value' => 'no'], 'cloud');
        $this->assertFalse($consolidation->refresh()->is_current);
        $this->assertDatabaseCount('guided_answers', 3);
        $this->expectException(ValidationException::class);
        $service->propose($consolidation, $target, $coordinator, 'partial', 3, 'Obsolete consolidation');
    }

    public function test_question_bank_rejects_multiple_active_criteria_for_same_target(): void
    {
        $standard = Standard::create(['code' => 'GENERIC', 'name' => 'Standard', 'edition' => '2']);
        $r = Requirement::create(['standard_id' => $standard->id, 'code' => '1.1', 'title' => 'One']);
        $actor = $this->actor('Editor', ['guided.bank.manage']);
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'BANK', 'name' => 'Double']);
        foreach (['Q1', 'Q2'] as $code) {
            $q = GuidedQuestion::create(['guided_question_bank_id' => $bank->id,
                'code' => $code, 'prompt' => 'A question?', 'answer_options' => ['yes', 'no']]);
            GuidedQuestionTarget::create(['guided_question_id' => $q->id, 'requirement_id' => $r->id,
                'criterion' => 'Repeated criterion']);
        }
        $this->assertNotEmpty(app(GuidedQuestionBankService::class)->coverage($bank)['duplicate_targets']);
        $this->expectException(ValidationException::class);
        app(GuidedQuestionBankService::class)->publish($bank, $actor);
    }
}
