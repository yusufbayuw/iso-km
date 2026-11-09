<?php

namespace Tests\Feature;

use App\Filament\Resources\GuidedAnswerResource;
use App\Filament\Resources\GuidedAssessmentResource;
use App\Filament\Resources\GuidedAssignmentResource;
use App\Filament\Resources\GuidedConsolidationResource;
use App\Filament\Resources\GuidedDecisionResource;
use App\Filament\Resources\GuidedExportTemplateResource;
use App\Filament\Resources\GuidedQuestionBankResource;
use App\Filament\Resources\GuidedQuestionResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedFilamentSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guided_assessment_navigation_pages_render_for_authorized_coordinator(): void
    {
        $user = User::create(['name' => 'Coordinator', 'email' => 'ui@example.test', 'password' => 'password']);
        $permissions = [
            'portal.access', 'guided.bank.manage', 'guided.template.manage',
            'guided.assessment.view', 'guided.assessment.manage',
            'guided.answer.submit', 'guided.consolidate',
            'guided.evidence.review', 'guided.assessment.review',
        ];
        foreach ($permissions as $name) { Permission::findOrCreate($name, 'web'); }
        $user->givePermissionTo($permissions);
        $this->actingAs($user);
        foreach ([
            GuidedQuestionBankResource::class, GuidedQuestionResource::class,
            GuidedAssessmentResource::class, GuidedAssignmentResource::class,
            GuidedAnswerResource::class, GuidedConsolidationResource::class,
            GuidedDecisionResource::class, GuidedExportTemplateResource::class,
        ] as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }
    }
}
