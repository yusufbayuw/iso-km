<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\GuidedAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class GuidedEvidenceAccessService
{
    /**
     * A respondent can attach their own records, or non-sensitive records in the
     * same management system when granted the existing evidence read permission.
     * A global evidence permission never exposes another owner's restricted file.
     */
    public function selectableQuery(GuidedAssignment $assignment, User $actor): Builder
    {
        if ($assignment->user_id !== $actor->id
            || ! $actor->can('guided.answer.submit')
            || ! $assignment->assessment->isMember($actor, ['respondent'])) {
            throw ValidationException::withMessages(['authorization' => 'Responden tidak berwenang menggunakan evidence ini.']);
        }
        return Evidence::query()
            ->where('management_system_id', $assignment->assessment->management_system_id)
            ->where('status', '!=', 'archived')
            ->where(function (Builder $query) use ($actor): void {
                $query->where('owner_id', $actor->id);
                if ($actor->can('view_any_evidence')) {
                    $query->orWhereIn('confidentiality', ['public', 'internal']);
                }
            });
    }

    public function assertSelectable(GuidedAssignment $assignment, User $actor, array $evidenceIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $evidenceIds)));
        if ($ids && ($this->selectableQuery($assignment, $actor)->whereIn('id', $ids)->count() !== count($ids))) {
            throw ValidationException::withMessages(['evidence' => 'Evidence di luar lingkup, rahasia, atau tidak dapat diakses.']);
        }
        return $ids;
    }
}
