<?php

namespace App\Services;

use App\Filament\Resources\AuditFindingResource;
use App\Filament\Resources\AuditResource;
use App\Filament\Resources\CompetencyRecordResource;
use App\Filament\Resources\CorrectiveActionResource;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\EvidenceRequestResource;
use App\Filament\Resources\GuidedAssignmentResource;
use App\Filament\Resources\GuidedDecisionResource;
use App\Filament\Resources\ManagementReviewResource;
use App\Filament\Resources\MyDocumentResource;
use App\Filament\Resources\MyTrainingResource;
use App\Filament\Resources\ObjectiveResource;
use App\Filament\Resources\RequirementAssessmentResource;
use App\Filament\Resources\RiskResource;
use App\Filament\Resources\StatementOfApplicabilityResource;
use App\Filament\Resources\SupplierAssessmentResource;
use App\Filament\Resources\SupplierResource;
use App\Models\ActionItem;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\CompetencyRecord;
use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\DocumentDistribution;
use App\Models\EvidenceRequest;
use App\Models\GuidedAssignment;
use App\Models\GuidedDecision;
use App\Models\ManagementReview;
use App\Models\ManagementReviewAction;
use App\Models\Objective;
use App\Models\RequirementAssessment;
use App\Models\Risk;
use App\Models\StatementOfApplicability;
use App\Models\Supplier;
use App\Models\SupplierAssessment;
use App\Models\TrainingAssignment;

class ActionSourceNavigationService
{
    public function url(ActionItem $item): ?string
    {
        $source = $item->source;
        if ($source === null) {
            return null;
        }

        $resource = match (true) {
            $source instanceof Audit => AuditResource::class,
            $source instanceof AuditFinding => AuditFindingResource::class,
            $source instanceof CorrectiveAction => CorrectiveActionResource::class,
            $source instanceof Document => DocumentResource::class,
            $source instanceof DocumentDistribution => MyDocumentResource::class,
            $source instanceof EvidenceRequest => EvidenceRequestResource::class,
            $source instanceof RequirementAssessment => RequirementAssessmentResource::class,
            $source instanceof StatementOfApplicability => StatementOfApplicabilityResource::class,
            $source instanceof Risk => RiskResource::class,
            $source instanceof Objective => ObjectiveResource::class,
            $source instanceof ManagementReview, $source instanceof ManagementReviewAction => ManagementReviewResource::class,
            $source instanceof Supplier => SupplierResource::class,
            $source instanceof SupplierAssessment => SupplierAssessmentResource::class,
            $source instanceof TrainingAssignment => MyTrainingResource::class,
            $source instanceof CompetencyRecord => CompetencyRecordResource::class,
            $source instanceof GuidedAssignment => GuidedAssignmentResource::class,
            $source instanceof GuidedDecision => GuidedDecisionResource::class,
            default => null,
        };

        return $resource && $resource::canViewAny() ? $resource::getUrl('index') : null;
    }
}
