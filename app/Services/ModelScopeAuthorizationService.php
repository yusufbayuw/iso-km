<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\CorrectiveAction;
use App\Models\CompetencyRecord;
use App\Models\ComplianceScope;
use App\Models\GuidedAssessment;
use App\Models\EvidenceReuse;
use App\Models\ManagementReviewAction;
use App\Models\ManagementSystem;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModelScopeAuthorizationService
{
    // Ownership on existing ComplianceScope is sufficient; no extra screens.
    public function isGlobal(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'iso_manager', 'iso_coordinator', 'top_management'])
            || $user->can('guided.system.manage_all');
    }

    public function canAccess(User $user, Model $record): bool
    {
        if ($this->isGlobal($user)) { return true; }
        $system = $this->systemFor($record);
        if (! $system) { return true; }
        if ($this->canCreateInSystem($user, (int) $system->getKey())) { return true; }
        $scopeOwner = $system->scope?->owner_id;
        if ($scopeOwner === null || (int) $scopeOwner === (int) $user->id) { return true; }

        if ($record->getAttribute('owner_id') !== null
            && (int) $record->getAttribute('owner_id') === (int) $user->id) {
            return true;
        }
        if (($record instanceof TrainingAssignment || $record instanceof CompetencyRecord)
            && $record->user_id !== null && (int) $record->user_id === (int) $user->id) {
            return true;
        }

        $audit = match (true) {
            $record instanceof Audit => $record,
            $record instanceof AuditFinding => $record->audit,
            $record instanceof CorrectiveAction => $record->finding?->audit,
            default => null,
        };
        return $audit !== null && (
            ($audit->lead_auditor_id !== null && (int) $audit->lead_auditor_id === (int) $user->id)
            || $audit->members()->where('users.id', $user->id)->exists()
        );
    }

    public function scopeQuery(Builder $query, ?User $user): Builder
    {
        if (! $user) { return $query->whereRaw('1 = 0'); }
        if ($this->isGlobal($user)) { return $query; }

        $model = $query->getModel();
        $relation = $this->scopeRelation($model);
        if (! $relation) { return $query; }

        return $query->where(function (Builder $q) use ($user, $model, $relation): void {
            $q->whereHas($relation, fn (Builder $scope) => $scope
                ->whereNull('owner_id')->orWhere('owner_id', $user->id));

            if (in_array($model::class, [
                \App\Models\Risk::class, \App\Models\Evidence::class, \App\Models\AuditFinding::class,
                \App\Models\CorrectiveAction::class, \App\Models\RequirementAssessment::class,
                \App\Models\StatementOfApplicability::class, \App\Models\Objective::class,
                \App\Models\TrainingProgram::class,
                \App\Models\EvidenceSchedule::class, \App\Models\EvidenceRequest::class,
            ], true)) {
                $q->orWhere('owner_id', $user->id);
            }
            if ($model instanceof TrainingAssignment || $model instanceof CompetencyRecord) {
                $q->orWhere('user_id', $user->id);
            }
            // Existing Guided Assessment coordinator assignments grant the
            // system itself, never unrestricted access to another scope.
            $assignedSystemIds = $this->guidedCoordinatorSystemIds($user);
            if ($model instanceof ManagementSystem) {
                $q->orWhereIn('id', $assignedSystemIds);
            } else {
                $systemRelation = $this->systemRelation($model);
                if ($systemRelation) {
                    $q->orWhereHas($systemRelation, fn (Builder $systems) =>
                        $systems->whereIn('id', $assignedSystemIds));
                }
            }
            if ($model instanceof Audit) {
                $q->orWhere('lead_auditor_id', $user->id)
                    ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
            }
            if ($model instanceof AuditFinding) {
                $q->orWhereHas('audit', fn (Builder $a) => $a->where('lead_auditor_id', $user->id))
                    ->orWhereHas('audit.members', fn (Builder $m) => $m->where('users.id', $user->id));
            }
            if ($model instanceof CorrectiveAction) {
                $q->orWhereHas('finding.audit', fn (Builder $a) => $a->where('lead_auditor_id', $user->id))
                    ->orWhereHas('finding.audit.members', fn (Builder $m) => $m->where('users.id', $user->id));
            }
        });
    }

    /**
     * Restrict new records to the actor's assigned systems; no new settings/UI.
     * The existing unset-owner scope remains available as in legacy releases.
     */
    public function canCreateInSystem(User $user, int $managementSystemId): bool
    {
        $system = ManagementSystem::query()->with('scope')->find($managementSystemId);
        if (! $system) { return false; }
        if ($this->isGlobal($user)) { return true; }
        if ($system->scope && (
            $system->scope->owner_id === null || (int) $system->scope->owner_id === (int) $user->id
        )) { return true; }

        return $this->guidedCoordinatorSystemIds($user)
            ->where('management_system_id', $managementSystemId)->exists();
    }

    public function canCreateInScope(User $user, int $scopeId): bool
    {
        $scope = ComplianceScope::query()->find($scopeId);
        return $scope !== null && (
            $this->isGlobal($user)
            || $scope->owner_id === null
            || (int) $scope->owner_id === (int) $user->id
        );
    }

    public function scopeAvailableSystems(Builder $query, ?User $user): Builder
    {
        if (! $user) { return $query->whereRaw('1 = 0'); }
        if ($this->isGlobal($user)) { return $query; }

        return $query->where(function (Builder $systems) use ($user): void {
            $systems->whereHas('scope', fn (Builder $scopes) =>
                $scopes->whereNull('owner_id')->orWhere('owner_id', $user->id))
                ->orWhereIn('id', $this->guidedCoordinatorSystemIds($user));
        });
    }

    public function scopeAvailableScopes(Builder $query, ?User $user): Builder
    {
        if (! $user) { return $query->whereRaw('1 = 0'); }
        if ($this->isGlobal($user)) { return $query; }
        return $query->where(fn (Builder $scopes) =>
            $scopes->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }

    private function guidedCoordinatorSystemIds(User $user): Builder
    {
        return GuidedAssessment::query()
            ->whereHas('memberships', fn (Builder $membership) =>
                $membership->where('user_id', $user->id)->where('role', 'coordinator'))
            ->select('management_system_id');
    }

    private function systemRelation(Model $model): ?string
    {
        $scope = $this->scopeRelation($model);
        return $scope && str_ends_with($scope, '.scope') ? substr($scope, 0, -6) : null;
    }

    private function scopeRelation(Model $model): ?string
    {
        return match (true) {
            $model instanceof ManagementSystem => 'scope',
            $model instanceof AuditFinding => 'audit.managementSystem.scope',
            $model instanceof CorrectiveAction => 'finding.audit.managementSystem.scope',
            $model instanceof TrainingAssignment => 'program.managementSystem.scope',
            $model instanceof ManagementReviewAction => 'managementReview.managementSystem.scope',
            $model instanceof EvidenceReuse => 'targetManagementSystem.scope',
            method_exists($model, 'managementSystem') => 'managementSystem.scope',
            default => null,
        };
    }

    private function systemFor(Model $model): ?ManagementSystem
    {
        return match (true) {
            $model instanceof ManagementSystem => $model,
            $model instanceof AuditFinding => $model->audit?->managementSystem,
            $model instanceof CorrectiveAction => $model->finding?->audit?->managementSystem,
            $model instanceof TrainingAssignment => $model->program?->managementSystem,
            $model instanceof ManagementReviewAction => $model->managementReview?->managementSystem,
            $model instanceof EvidenceReuse => $model->targetManagementSystem,
            method_exists($model, 'managementSystem') => $model->managementSystem,
            default => null,
        };
    }
}
