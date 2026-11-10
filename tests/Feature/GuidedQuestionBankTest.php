<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\GuidedAssessment;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use App\Services\GuidedQuestionBankService;
use App\Services\GuidedQuestionBranchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_question_bank_is_generic_and_idempotent(): void
    {
        $standard = Standard::create(['code' => 'STANDARD-X', 'name' => 'Standard X', 'edition' => '1']);
        Requirement::create(['standard_id' => $standard->id, 'code' => '2.1', 'title' => 'Data governance']);
        Control::create(['standard_id' => $standard->id, 'code' => 'C1', 'title' => 'Control one']);
        $actor = User::create(['name' => 'Administrator', 'email' => 'bank@example.test', 'password' => 'password']);
        Permission::findOrCreate('guided.bank.manage', 'web');
        $actor->givePermissionTo('guided.bank.manage');
        $bank = GuidedQuestionBank::create([
            'standard_id' => $standard->id, 'code' => 'B', 'name' => 'Starter',
        ]);
        $service = app(GuidedQuestionBankService::class);
        $this->assertSame(2, $service->generateStarterQuestions($bank, $actor));
        $this->assertSame(2, $service->generateStarterQuestions($bank, $actor));
        $this->assertSame(2, $bank->questions()->count());
        $this->assertSame(2, GuidedQuestionTarget::count());
        $coverage = $service->publish($bank, $actor);
        $this->assertSame(1, $coverage['requirements_covered']);
        $this->assertSame(1, $coverage['controls_covered']);
        $this->assertSame('published', $bank->refresh()->status);
    }

    public function test_branching_assigns_only_relevant_followup_to_respondent(): void
    {
        $standard = Standard::create(['code' => 'STANDARD-Y', 'name' => 'Standard Y', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'S', 'name' => 'Scope']);
        $system = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'SYS', 'name' => 'System',
        ]);
        $requirement = Requirement::create(['standard_id' => $standard->id, 'code' => '1', 'title' => 'Requirement']);
        $control = Control::create(['standard_id' => $standard->id, 'code' => 'A.1', 'title' => 'Control']);
        $manager = User::create(['name' => 'Coordinator', 'email' => 'coordinator@example.test', 'password' => 'password']);
        $user = User::create(['name' => 'Respondent', 'email' => 'respondent@example.test', 'password' => 'password']);
        foreach (['guided.bank.manage', 'guided.assessment.manage', 'guided.answer.submit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $manager->givePermissionTo(['guided.bank.manage', 'guided.assessment.manage']);
        $user->givePermissionTo('guided.answer.submit');
        $bank = GuidedQuestionBank::create(['standard_id' => $standard->id, 'code' => 'B', 'name' => 'Branching']);
        $root = GuidedQuestion::create([
            'guided_question_bank_id' => $bank->id, 'code' => 'ROOT', 'prompt' => 'Is the control used?',
            'answer_options' => ['yes', 'no'], 'branch_rules' => [['when' => 'yes', 'next_code' => 'DETAIL']],
        ]);
        $detail = GuidedQuestion::create([
            'guided_question_bank_id' => $bank->id, 'code' => 'DETAIL', 'prompt' => 'Provide operational detail?',
            'answer_options' => ['yes', 'no'],
        ]);
        GuidedQuestionTarget::create([
            'guided_question_id' => $root->id, 'requirement_id' => $requirement->id, 'criterion' => 'Declaration',
        ]);
        GuidedQuestionTarget::create([
            'guided_question_id' => $root->id, 'control_id' => $control->id, 'criterion' => 'Operational control',
        ]);
        app(GuidedQuestionBankService::class)->publish($bank, $manager);
        $run = GuidedAssessment::create([
            'management_system_id' => $system->id, 'guided_question_bank_id' => $bank->id,
            'name' => 'Branch test', 'created_by' => $manager->id,
        ]);
        $this->assertSame(1, app(GuidedQuestionBranchingService::class)->assignRoots($run, $user, $manager));
        $assignment = $run->assignments()->firstOrFail();
        app(GuidedAssessmentWorkflowService::class)->submitAnswer($assignment, $user, ['value' => 'yes']);
        $this->assertSame(2, $run->assignments()->count());
        $followup = $run->assignments()->where('guided_question_id', $detail->id)->firstOrFail();
        $this->assertSame('assigned', $followup->status);
        $this->assertTrue($followup->is_auto_assigned);

        $service = app(GuidedAssessmentWorkflowService::class);
        $service->submitAnswer($assignment, $user, ['value' => 'no']);
        $this->assertSame('skipped', $followup->refresh()->status);
        try {
            $service->submitAnswer($followup, $user, ['value' => 'yes']);
            $this->fail('An inactive follow-up must reject new answers.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment', $exception->errors());
        }

        $service->submitAnswer($assignment, $user, ['value' => 'yes'], 'database');
        $this->assertSame('assigned', $followup->refresh()->status);
        $service->submitAnswer($followup, $user, ['value' => 'yes']);
        $this->assertSame('submitted', $followup->refresh()->status);
        $service->submitAnswer($assignment, $user, ['value' => 'no'], 'general');
        $this->assertSame('submitted', $followup->refresh()->status,
            'A different active context must keep the branch available.');
        $service->submitAnswer($assignment, $user, ['value' => 'no'], 'database');
        $this->assertSame('skipped', $followup->refresh()->status);
        $this->assertDatabaseCount('guided_answers', 6);
    }
}
