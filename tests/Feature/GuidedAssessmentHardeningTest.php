<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\GuidedAssessment;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\GuidedScopeItem;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Risk;
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

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('iso-km.guided_assessment_enabled', true);
    }

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

    public function test_created_by_form_fields_cannot_impersonate_another_user(): void
    {
        extract($this->fixture());
        $other = $this->actor('OtherPerson');
        $this->actingAs($coordinator);
        $second = GuidedAssessment::create([
            'management_system_id' => $system->id, 'guided_question_bank_id' => $bank->id,
            'name' => 'Second run', 'created_by' => $other->id,
        ]);
        $this->assertSame($coordinator->id, $second->created_by);
        $this->assertTrue($second->isMember($coordinator, ['coordinator']));
        $item = GuidedScopeItem::create([
            'guided_assessment_id' => $run->id, 'kind' => 'asset',
            'title' => 'Scoped asset', 'created_by' => $other->id,
        ]);
        $this->assertSame($coordinator->id, $item->created_by);
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
        $this->assertCount(30, $coverage['catalog_missing_requirements']);
        $this->assertSame(0, $coverage['requirements_total'], 'Clause headings are not assessable subclauses.');
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

    public function test_linked_risk_cannot_be_understated_or_approved_after_its_score_increases(): void
    {
        extract($this->fixture());
        $service = app(GuidedAssessmentWorkflowService::class);
        $riskOwner = $this->actor('Riskowner', ['guided.risk.approve']);
        $assessor = $this->actor('Assessor', ['guided.assessment.review']);
        $run->memberships()->create(['user_id' => $riskOwner->id, 'role' => 'risk_owner']);
        $run->memberships()->create(['user_id' => $assessor->id, 'role' => 'assessor']);

        $risk = Risk::create(['management_system_id' => $system->id, 'owner_id' => $riskOwner->id,
            'code' => 'R001', 'title' => 'Important risk', 'likelihood' => 4, 'impact' => 4, 'status' => 'open']);
        $risk->requirements()->attach($requirement);
        $answer = $service->submitAnswer($assignment, $respondent, ['value' => 'no']);
        $consolidation = $service->consolidate($run, $question, $coordinator, [$answer->id],
            'Requirement is not implemented.', 'select');
        $decision = $service->propose($consolidation, $target, $coordinator,
            'nonconformant', 2, 'Observed gap.', $riskOwner->id);
        $this->assertSame(16, $decision->risk_score);

        $risk->update(['likelihood' => 5, 'impact' => 5]);
        try {
            $service->approve($decision, $assessor, 'Review completed.');
            $this->fail('Approval cannot proceed using an obsolete risk score.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('risk', $exception->errors());
        }
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
