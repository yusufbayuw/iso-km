<?php

namespace Tests\Feature;

use App\Models\ComplianceScope;
use App\Models\ManagementReview;
use App\Models\ManagementReviewAction;
use App\Models\ManagementSystem;
use App\Models\Objective;
use App\Models\ObjectiveMeasurement;
use App\Models\Standard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_objectives_and_management_review_actions_are_traceable_to_management_system(): void
    {
        $standard = Standard::create(['code' => 'ISO/IEC 27001', 'name' => 'ISMS', 'edition' => '2022']);
        $scope = ComplianceScope::create(['code' => 'ORG', 'name' => 'Organization']);
        $system = ManagementSystem::create(['standard_id' => $standard->id, 'compliance_scope_id' => $scope->id, 'code' => 'ISMS', 'name' => 'ISMS']);
        $objective = Objective::create(['management_system_id' => $system->id, 'code' => 'OBJ-001', 'title' => 'Improve awareness', 'target_value' => 95]);
        $measurement = ObjectiveMeasurement::create(['objective_id' => $objective->id, 'period_date' => now()->startOfMonth(), 'actual_value' => 90, 'status' => 'at_risk']);
        $review = ManagementReview::create(['management_system_id' => $system->id, 'code' => 'MR-001', 'title' => 'Quarterly Management Review']);
        $action = ManagementReviewAction::create(['management_review_id' => $review->id, 'action' => 'Increase awareness completion rate']);

        $this->assertTrue($objective->managementSystem->is($system));
        $this->assertTrue($objective->measurements->contains($measurement));
        $this->assertTrue($review->actions->contains($action));
    }
}
