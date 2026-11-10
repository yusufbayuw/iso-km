<?php

namespace Tests\Feature;

use App\Filament\Resources\GuidedAssessmentResource;
use App\Filament\Resources\GuidedExportTemplateResource;
use App\Models\ComplianceScope;
use App\Models\GuidedAssessment;
use App\Models\GuidedExportTemplate;
use App\Models\GuidedQuestion;
use App\Models\GuidedQuestionBank;
use App\Models\ManagementSystem;
use App\Models\Standard;
use App\Models\User;
use App\Services\GuidedAssessmentWorkflowService;
use App\Services\GuidedSystemAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedFeatureScopeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $abilities): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'password',
        ]);
        foreach ($abilities as $ability) { Permission::findOrCreate($ability, 'web'); }
        $user->givePermissionTo($abilities);
        return $user;
    }

    private function systems(User $owner, User $outsider): array
    {
        $standard = Standard::create(['code' => 'STANDARD-Z', 'name' => 'Standard Z', 'edition' => '1']);
        $ownedScope = ComplianceScope::create(['code' => 'OWNED', 'name' => 'Owned scope', 'owner_id' => $owner->id]);
        $foreignScope = ComplianceScope::create(['code' => 'FOREIGN', 'name' => 'Other scope', 'owner_id' => $outsider->id]);
        $owned = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $ownedScope->id,
            'code' => 'OWN', 'name' => 'Owned system',
        ]);
        $foreign = ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $foreignScope->id,
            'code' => 'OTH', 'name' => 'Other system',
        ]);
        $bank = GuidedQuestionBank::create([
            'standard_id' => $standard->id, 'code' => 'BANK', 'name' => 'Bank', 'status' => 'published',
        ]);
        $question = GuidedQuestion::create([
            'guided_question_bank_id' => $bank->id, 'code' => 'Q1', 'prompt' => 'Evidence?', 'answer_options' => ['yes', 'no'],
        ]);
        return compact('owned', 'foreign', 'bank', 'question');
    }

    public function test_disabled_feature_hides_routes_and_rejects_domain_actions(): void
    {
        config()->set('iso-km.guided_assessment_enabled', false);
        $owner = $this->user('Owner', [
            'portal.access', 'guided.assessment.view', 'guided.assessment.manage',
            'guided.template.manage', 'guided.bank.manage',
        ]);
        $outsider = $this->user('Outsider', []);
        extract($this->systems($owner, $outsider));
        $this->actingAs($owner);
        $this->get(GuidedAssessmentResource::getUrl('index'))->assertForbidden();
        $this->get(GuidedExportTemplateResource::getUrl('index'))->assertForbidden();
        $this->assertFalse(GuidedAssessmentResource::canViewAny());
        $this->assertFalse(GuidedExportTemplateResource::canViewAny());

        // Model fixture is created without an authenticated actor so it can
        // represent records that existed before a feature was disabled.
        auth()->logout();
        $run = GuidedAssessment::create([
            'management_system_id' => $owned->id, 'guided_question_bank_id' => $bank->id,
            'name' => 'Pre-existing run', 'created_by' => $owner->id,
        ]);
        $this->actingAs($owner);
        try {
            app(GuidedAssessmentWorkflowService::class)->assign($run, $question, $owner, $owner);
            $this->fail('Server-side workflow must not bypass the disabled feature.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('guided_assessment', $exception->errors());
        }
    }

    public function test_template_scope_and_assessment_creation_are_checked_server_side(): void
    {
        config()->set('iso-km.guided_assessment_enabled', true);
        $owner = $this->user('Owner', [
            'portal.access', 'guided.assessment.view', 'guided.assessment.manage',
            'guided.template.manage',
        ]);
        $outsider = $this->user('Outsider', []);
        extract($this->systems($owner, $outsider));
        $access = app(GuidedSystemAccessService::class);
        $this->assertTrue($access->canAccess($owner, $owned->id));
        $this->assertFalse($access->canAccess($owner, $foreign->id));

        $this->actingAs($owner);
        $run = GuidedAssessment::create([
            'management_system_id' => $owned->id, 'guided_question_bank_id' => $bank->id,
            'name' => 'Owned assessment', 'created_by' => $outsider->id,
        ]);
        $this->assertSame($owner->id, $run->created_by);
        try {
            GuidedAssessment::create([
                'management_system_id' => $foreign->id, 'guided_question_bank_id' => $bank->id,
                'name' => 'Forged assessment', 'created_by' => $owner->id,
            ]);
            $this->fail('Assessment creation must reject a foreign management system.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('management_system_id', $exception->errors());
        }

        $ownedTemplate = GuidedExportTemplate::create([
            'management_system_id' => $owned->id, 'name' => 'Local',
            'file_path' => 'iso/guided/templates/local.xlsx', 'mappings' => [],
        ]);
        $this->assertSame($owner->id, $ownedTemplate->created_by);
        try {
            GuidedExportTemplate::create([
                'management_system_id' => $foreign->id, 'name' => 'Foreign',
                'file_path' => 'iso/guided/templates/foreign.xlsx', 'mappings' => [],
            ]);
            $this->fail('Cannot create a template in an inaccessible management system.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('management_system_id', $exception->errors());
        }
        try {
            GuidedExportTemplate::create([
                'management_system_id' => null, 'name' => 'Forged global',
                'file_path' => 'iso/guided/templates/global.xlsx', 'mappings' => [],
            ]);
            $this->fail('Ordinary users must not create global templates.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('management_system_id', $exception->errors());
        }

        $admin = $this->user('Administrator', [
            'guided.template.manage', 'guided.template.manage_global',
            'guided.system.manage_all',
        ]);
        $this->actingAs($admin);
        $global = GuidedExportTemplate::create([
            'management_system_id' => null, 'name' => 'Global',
            'file_path' => 'iso/guided/templates/global.xlsx', 'mappings' => [],
        ]);
        $foreignTemplate = GuidedExportTemplate::create([
            'management_system_id' => $foreign->id, 'name' => 'Other',
            'file_path' => 'iso/guided/templates/other.xlsx', 'mappings' => [],
        ]);
        $this->actingAs($owner);
        $visible = GuidedExportTemplateResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($ownedTemplate->id, $visible);
        $this->assertNotContains($foreignTemplate->id, $visible);
        $this->assertNotContains($global->id, $visible);
        try {
            $global->update(['management_system_id' => $owned->id]);
            $this->fail('Cannot move a global template into an accessible scope.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('management_system_id', $exception->errors());
        }
    }
}
