<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\GuidedAssessment;
use App\Models\GuidedAssignment;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\GuidedQuestionTarget;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedEvidenceUploadService;
use App\Services\GuidedQuestionBankService;
use App\Services\GuidedWizardDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedEvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('iso-km.guided_assessment_enabled', true);
        config()->set('iso-km.guided_wizard_enabled', true);
        Storage::fake('local');
    }

    private function fixture(): array
    {
        $actor = User::factory()->create();
        foreach (['guided.assessment.manage', 'guided.answer.submit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $actor->givePermissionTo(['guided.assessment.manage', 'guided.answer.submit']);
        $this->actingAs($actor);

        $standard = Standard::create([
            'code' => 'EVIDENCE-STANDARD', 'name' => 'Evidence Standard', 'edition' => '2026',
        ]);
        $scope = ComplianceScope::create([
            'code' => 'UPLOAD-UNIT', 'name' => 'Upload Unit', 'owner_id' => $actor->id,
        ]);
        $system = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'UPLOAD-MS', 'name' => 'Upload System',
        ]);
        $requirement = Requirement::create([
            'standard_id' => $standard->id, 'code' => '4.1', 'title' => 'Context',
        ]);
        $control = Control::create([
            'standard_id' => $standard->id, 'code' => 'A.5.1',
            'title' => 'Policy', 'is_group' => false,
        ]);
        $bank = GuidedQuestionBank::create([
            'standard_id' => $standard->id, 'code' => 'UPLOAD-BANK',
            'name' => 'Upload Bank',
        ]);
        $question = GuidedQuestion::create([
            'guided_question_bank_id' => $bank->id, 'code' => 'Q-UPLOAD',
            'prompt' => 'Is this evidenced?', 'answer_type' => 'single_choice',
            'answer_options' => ['yes', 'no'],
        ]);
        GuidedQuestionTarget::create([
            'guided_question_id' => $question->id, 'requirement_id' => $requirement->id,
            'criterion' => 'Requirement is supported',
        ]);
        GuidedQuestionTarget::create([
            'guided_question_id' => $question->id, 'control_id' => $control->id,
            'criterion' => 'Control is supported',
        ]);
        Permission::findOrCreate('guided.bank.manage', 'web');
        $actor->givePermissionTo('guided.bank.manage');
        app(GuidedQuestionBankService::class)->publish($bank, $actor);
        $run = GuidedAssessment::create([
            'management_system_id' => $system->id,
            'guided_question_bank_id' => $bank->id,
            'name' => 'Assessment for upload',
            'created_by' => $actor->id,
        ]);
        $run->memberships()->firstOrCreate([
            'user_id' => $actor->id, 'role' => 'respondent',
        ]);
        $assignment = GuidedAssignment::create([
            'guided_assessment_id' => $run->id,
            'guided_question_id' => $question->id,
            'user_id' => $actor->id,
            'status' => 'assigned',
        ]);

        return compact('actor', 'run', 'assignment', 'system', 'requirement', 'control');
    }

    public function test_upload_registers_private_evidence_and_attaches_it_to_draft_answer_and_targets(): void
    {
        extract($this->fixture());
        $service = app(GuidedEvidenceUploadService::class);

        $draft = $service->uploadAndAttach(
            $assignment, $actor, 'general', 'yes', 'Evidence attached',
            [], 0, [UploadedFile::fake()->create('policy.pdf', 50, 'application/pdf')]
        );
        $this->assertCount(1, $draft->evidence_ids);
        $evidence = Evidence::query()->findOrFail($draft->evidence_ids[0]);

        $this->assertSame($system->id, $evidence->management_system_id);
        $this->assertSame($actor->id, $evidence->owner_id);
        $this->assertSame('policy.pdf', $evidence->title);
        $this->assertSame('pending', $evidence->verification_status);
        $this->assertSame('confidential', $evidence->confidentiality);
        $this->assertSame([$requirement->id], $evidence->requirements->modelKeys());
        $this->assertSame([$control->id], $evidence->controls->modelKeys());
        $this->assertStringStartsWith('iso/evidence/', $evidence->file_path);
        Storage::disk('local')->assertExists($evidence->file_path);
        Storage::disk('public')->assertMissing($evidence->file_path);

        $answer = app(GuidedWizardDraftService::class)->submit(
            $assignment, $actor, 'general'
        );
        $this->assertSame([$evidence->id], $answer->evidences->modelKeys());
        $this->assertDatabaseCount('evidences', 1);
    }

    public function test_native_wizard_upload_action_creates_and_selects_evidence_once(): void
    {
        extract($this->fixture());
        foreach (['guided.assessment.view', 'portal.access'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $actor->givePermissionTo(['guided.assessment.view', 'portal.access']);

        $page = \Livewire\Livewire::test(\App\Filament\Pages\StartAssessment::class)
            ->assertSee('Unggah bukti baru')
            ->set('uploadData.files', [
                UploadedFile::fake()->create('wizard.pdf', 10, 'application/pdf'),
            ])
            ->call('uploadEvidence')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evidences', 1);
        $evidence = Evidence::query()->firstOrFail();
        $this->assertContains($evidence->id, $page->get('answerData')['evidence_ids'] ?? []);
        $page->call('saveDraft')->assertHasNoErrors();
        $this->assertDatabaseCount('evidences', 1);

        $draft = \App\Models\GuidedAnswerDraft::query()
            ->where('guided_assignment_id', $assignment->id)->firstOrFail();
        $this->assertSame([$evidence->id], $draft->evidence_ids);
    }

    public function test_save_and_send_buttons_process_pending_upload_without_duplicate_records(): void
    {
        extract($this->fixture());
        foreach (['guided.assessment.view', 'portal.access'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $actor->givePermissionTo(['guided.assessment.view', 'portal.access']);

        $page = \Livewire\Livewire::test(\App\Filament\Pages\StartAssessment::class)
            ->set('answerData.value', 'yes')
            ->set('uploadData.files', [
                UploadedFile::fake()->create('inline.pdf', 10, 'application/pdf'),
            ])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $evidence = Evidence::query()->firstOrFail();
        $draft = \App\Models\GuidedAnswerDraft::query()
            ->where('guided_assignment_id', $assignment->id)->firstOrFail();
        $this->assertContains($evidence->id, $draft->evidence_ids);

        $page->call('submitAnswer')->assertHasNoErrors();
        $answer = \App\Models\GuidedAnswer::query()
            ->where('guided_assignment_id', $assignment->id)->firstOrFail();
        $this->assertSame([$evidence->id], $answer->evidences->modelKeys());
        $this->assertDatabaseCount('evidences', 1);
    }

    public function test_another_user_cannot_upload_into_someone_elses_assignment(): void
    {
        extract($this->fixture());
        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        try {
            app(GuidedEvidenceUploadService::class)->uploadAndAttach(
                $assignment, $intruder, 'general', 'yes', null, [], 0,
                [UploadedFile::fake()->create('foreign.pdf', 10, 'application/pdf')]
            );
            $this->fail('An unrelated user must not be able to upload evidence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('authorization', $exception->errors());
        }

        $this->assertDatabaseCount('evidences', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('iso/evidence'));
    }

    public function test_invalid_file_is_rejected_without_creating_evidence(): void
    {
        extract($this->fixture());
        $this->expectException(ValidationException::class);
        try {
            app(GuidedEvidenceUploadService::class)->uploadAndAttach(
                $assignment, $actor, 'general', 'yes', null, [], 0,
                [UploadedFile::fake()->create('executable.exe', 10, 'application/x-msdownload')]
            );
        } finally {
            $this->assertDatabaseCount('evidences', 0);
        }
    }

    public function test_stale_draft_version_cleans_up_uploaded_file_and_evidence(): void
    {
        extract($this->fixture());
        app(GuidedWizardDraftService::class)->saveDraft(
            $assignment, $actor, 'general', 'yes', null, [], 0
        );

        try {
            app(GuidedEvidenceUploadService::class)->uploadAndAttach(
                $assignment, $actor, 'general', 'yes', null, [], 0,
                [UploadedFile::fake()->create('stale.pdf', 10, 'application/pdf')]
            );
            $this->fail('A stale draft version must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draft', $exception->errors());
        }
        $this->assertDatabaseCount('evidences', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('iso/evidence'));
    }

    public function test_locked_assessment_rejects_upload_without_storing_file(): void
    {
        extract($this->fixture());
        $run->update(['locked_at' => now(), 'status' => 'locked']);
        $this->expectException(ValidationException::class);
        try {
            app(GuidedEvidenceUploadService::class)->uploadAndAttach(
                $assignment, $actor, 'general', 'yes', null, [], 0,
                [UploadedFile::fake()->create('locked.pdf', 10, 'application/pdf')]
            );
        } finally {
            $this->assertDatabaseCount('evidences', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('iso/evidence'));
        }
    }
}
