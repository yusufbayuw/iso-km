<?php

namespace Tests\Feature;

use App\Filament\Pages\StartAssessment;
use App\Models\ComplianceScope;
use App\Models\Control;
use App\Models\GuidedAnswer;
use App\Models\GuidedAnswerDraft;
use App\Models\GuidedExportTemplate;
use App\Models\GuidedQuestionBank;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedQuestionBankService;
use App\Services\GuidedWizardDraftService;
use App\Services\GuidedWorkbookExportService;
use Database\Seeders\Iso27001GuidedStarterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class GuidedWizardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('iso-km.guided_assessment_enabled', true);
        config()->set('iso-km.guided_wizard_enabled', true);
    }

    private function prepare(): array
    {
        $this->seed(Iso27001GuidedStarterSeeder::class);
        foreach (['guided.bank.manage', 'guided.assessment.manage',
            'guided.assessment.view', 'guided.answer.submit', 'guided.report.export'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $actor = User::create([
            'name' => 'Coordinator', 'email' => 'wizard-coordinator@example.test',
            'password' => 'password',
        ]);
        $actor->givePermissionTo(['guided.bank.manage','guided.assessment.manage',
            'guided.assessment.view','guided.answer.submit','guided.report.export']);
        $standard = Standard::query()->where('code', 'ISO/IEC 27001')->where('edition', '2022')->firstOrFail();
        $scope = ComplianceScope::create([
            'code' => 'SC-WIZ', 'name' => 'Scoped department', 'owner_id' => $actor->id,
        ]);
        $system = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'MS-WIZ', 'name' => 'Scoped security management',
        ]);
        $bank = GuidedQuestionBank::query()->where('standard_id', $standard->id)
            ->where('code', 'ISO27001-2022-WIZARD')->firstOrFail();
        app(GuidedQuestionBankService::class)->publish($bank, $actor);
        $this->actingAs($actor);
        return compact('actor', 'standard', 'system', 'bank');
    }

    public function test_starter_seeder_is_complete_idempotent_and_has_no_example_organization(): void
    {
        $this->seed(Iso27001GuidedStarterSeeder::class);
        $this->seed(Iso27001GuidedStarterSeeder::class);
        $standard = Standard::query()->where('code', 'ISO/IEC 27001')
            ->where('edition', '2022')->firstOrFail();
        $this->assertSame(30, Requirement::query()->where('standard_id', $standard->id)
            ->whereNotIn('code', ['4','5','6','7','8','9','10'])->count());
        $this->assertSame(93, Control::query()->where('standard_id', $standard->id)
            ->where('is_group', false)->count());
        $this->assertSame(4, Control::query()->where('standard_id', $standard->id)
            ->where('is_group', true)->count());
        $bank = GuidedQuestionBank::query()->where('code', 'ISO27001-2022-WIZARD')->firstOrFail();
        $this->assertSame('draft', $bank->status);
        $this->assertSame(216, $bank->questions()->count());
        $this->assertSame(123, \App\Models\GuidedQuestionTarget::query()
            ->whereHas('question', fn ($query) => $query
                ->where('guided_question_bank_id', $bank->id))->count());
        $this->assertDatabaseCount('management_systems', 0);
        $this->assertDatabaseCount('compliance_scopes', 0);
        $this->assertDatabaseCount('guided_export_templates', 1);
        $template = GuidedExportTemplate::query()->firstOrFail();
        $this->assertGreaterThan(3000, count($template->mappings));
        $file = Storage::disk('local')->path($template->file_path);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($file) === true);
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $this->assertStringNotContainsString('Taruna Bakti', implode(' ', [
            $zip->getFromName('xl/worksheets/sheet1.xml'),
            $zip->getFromName('xl/workbook.xml'),
        ]));
        $zip->close();
    }

    public function test_start_save_resume_submit_revision_and_draft_export(): void
    {
        extract($this->prepare());
        $service = app(GuidedWizardDraftService::class);
        $run = $service->start($actor, $system->id, 'Assessment for unit A');
        $this->assertSame(123, $run->assignments()->count());
        $this->assertTrue($run->isMember($actor, ['coordinator']));
        $this->assertTrue($run->isMember($actor, ['respondent']));
        $assignment = $run->assignments()->whereHas('question', fn ($q) =>
            $q->where('code', 'REQ-4.1'))->firstOrFail();

        $draft = $service->saveDraft($assignment, $actor, 'general',
            'Diterapkan Sebagian', 'Current state', [], 0);
        $this->assertSame(1, $draft->version);
        $this->assertSame('Diterapkan Sebagian', $draft->fresh()->response['value']);
        $changed = $service->saveDraft($assignment, $actor, 'general',
            'Diterapkan Penuh', 'New record', [], $draft->version);
        $this->assertSame(2, $changed->version);

        try {
            $service->saveDraft($assignment, $actor, 'general', 'Belum Diterapkan', '', [], 1);
            $this->fail('Overwriting a newer draft must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('draft', $e->errors());
        }

        $answer = $service->submit($assignment, $actor, 'general');
        $this->assertSame('Diterapkan Penuh', $answer->response['value']);
        $service->saveDraft($assignment, $actor, 'general', 'Diterapkan Sebagian', '', [], 2);
        $revised = $service->submit($assignment, $actor, 'general');
        $this->assertSame($answer->id, $revised->supersedes_id);
        $this->assertDatabaseCount('guided_answers', 2);
        $this->assertDatabaseCount('guided_answer_drafts', 1);

        $template = GuidedExportTemplate::query()->firstOrFail();
        $path = app(GuidedWorkbookExportService::class)->export($run, $template, $actor, false);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)) === true);
        $s = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('Belum Dinilai', $s);
        $zip->close();
    }

    public function test_wizard_respects_default_off_and_system_ownership(): void
    {
        extract($this->prepare());
        config()->set('iso-km.guided_wizard_enabled', false);
        $this->assertFalse(StartAssessment::canAccess());
        try {
            app(GuidedWizardDraftService::class)->start($actor, $system->id, 'Disabled wizard');
            $this->fail('Wizard must fail closed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('wizard', $e->errors());
        }
        config()->set('iso-km.guided_wizard_enabled', true);
        $foreign = User::create(['name' => 'Foreign', 'email' => 'foreign-wizard@example.test', 'password' => 'password']);
        $foreignScope = ComplianceScope::create([
            'code' => 'SC-FOREIGN', 'name' => 'Foreign scope', 'owner_id' => $foreign->id,
        ]);
        $foreignSystem = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $foreignScope->id,
            'code' => 'MS-FOREIGN', 'name' => 'Foreign system',
        ]);
        try {
            app(GuidedWizardDraftService::class)->start($actor, $foreignSystem->id, 'Forged');
            $this->fail('Cross-system assessment creation must be blocked');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('management_system_id', $e->errors());
        }
    }
}
