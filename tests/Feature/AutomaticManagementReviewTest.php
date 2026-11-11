<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\ComplianceScope;
use App\Models\ManagementReview;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\Standard;
use App\Models\User;
use App\Services\ActionItemSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticManagementReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_review_collects_real_context_automatically_and_preserves_human_notes(): void
    {
        $standard = Standard::create(['code' => 'STD', 'name' => 'Standard']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'SYS', 'name' => 'System']);
        $requirement = Requirement::create(['standard_id' => $standard->id, 'code' => '4', 'title' => 'Context']);
        RequirementAssessment::create(['management_system_id' => $system->id, 'requirement_id' => $requirement->id, 'status' => 'nonconformant']);
        Risk::create(['management_system_id' => $system->id, 'code' => 'R-001', 'title' => 'Risk', 'likelihood' => 4, 'impact' => 4]);
        $owner = User::create(['name' => 'Owner', 'email' => 'review-owner@example.test', 'password' => 'password']);
        $audit = Audit::create(['management_system_id' => $system->id, 'code' => 'AUD-01', 'title' => 'Internal']);
        AuditFinding::create(['audit_id' => $audit->id, 'code' => 'F-001', 'title' => 'Finding', 'description' => 'Gap', 'owner_id' => $owner->id]);

        app(ActionItemSyncService::class)->sync();
        $work = ActionItem::query()->where('category', 'finding_response')->firstOrFail();
        $this->assertSame($owner->id, $work->owner_id);
        app(ActionItemSyncService::class)->sync();
        $this->assertSame(1, ActionItem::query()->where('category', 'finding_response')->count());

        $review = ManagementReview::create(['management_system_id' => $system->id, 'code' => 'MR-01', 'title' => 'Review']);
        $this->assertStringContainsString('Requirement belum sesuai / parsial: 1', $review->inputs_summary);
        $this->assertStringContainsString('Risiko terbuka tinggi: 1', $review->inputs_summary);
        $this->assertStringContainsString('Temuan audit belum ditutup: 1', $review->inputs_summary);
        $this->assertStringContainsString('Sistem: SYS', $review->inputs_summary);

        $manual = ManagementReview::create([
            'management_system_id' => $system->id,
            'code' => 'MR-02',
            'title' => 'Review Manual',
            'inputs_summary' => 'Keputusan dan agenda disiapkan oleh pimpinan.',
        ]);
        $this->assertSame('Keputusan dan agenda disiapkan oleh pimpinan.', $manual->fresh()->inputs_summary);
    }
}
