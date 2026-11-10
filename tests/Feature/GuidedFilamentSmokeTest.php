<?php

namespace Tests\Feature;

use App\Filament\Resources\GuidedAnswerResource;
use App\Filament\Resources\GuidedAssessmentResource;
use App\Filament\Resources\GuidedAssignmentResource;
use App\Filament\Resources\GuidedConsolidationResource;
use App\Filament\Resources\GuidedDecisionResource;
use App\Filament\Resources\GuidedExportTemplateResource;
use App\Filament\Resources\GuidedExportRecordResource;
use App\Filament\Resources\GuidedQuestionBankResource;
use App\Filament\Resources\GuidedScopeItemResource;
use App\Filament\Resources\GuidedQuestionResource;
use App\Models\User;
use App\Models\Standard;
use App\Models\ComplianceScope;
use App\Models\ManagementSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedFilamentSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('iso-km.guided_assessment_enabled', true);
    }

    public function test_guided_assessment_navigation_pages_render_for_authorized_coordinator(): void
    {
        $user = User::create(['name' => 'Coordinator', 'email' => 'ui@example.test', 'password' => 'password']);
        $permissions = [
            'portal.access', 'guided.bank.manage', 'guided.template.manage',
            'guided.assessment.view', 'guided.assessment.manage',
            'guided.answer.submit', 'guided.consolidate',
            'guided.evidence.review', 'guided.assessment.review', 'guided.report.export',
        ];
        foreach ($permissions as $name) { Permission::findOrCreate($name, 'web'); }
        $user->givePermissionTo($permissions);
        $standard = Standard::create(['code' => 'DEMO', 'name' => 'Generic standard', 'edition' => '1']);
        $scope = ComplianceScope::create(['code' => 'OWN', 'name' => 'Owned scope', 'owner_id' => $user->id]);
        ManagementSystem::create([
            'standard_id' => $standard->id, 'compliance_scope_id' => $scope->id,
            'code' => 'OWN', 'name' => 'Owned system',
        ]);
        $this->actingAs($user);
        foreach ([
            GuidedQuestionBankResource::class, GuidedQuestionResource::class,
            GuidedAssessmentResource::class, GuidedAssignmentResource::class,
            GuidedAnswerResource::class, GuidedConsolidationResource::class,
            GuidedDecisionResource::class, GuidedScopeItemResource::class,
            GuidedExportTemplateResource::class,
            GuidedExportRecordResource::class,
        ] as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }
    }
}
