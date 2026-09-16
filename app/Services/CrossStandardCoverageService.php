<?php

namespace App\Services;

use App\Models\ControlMapping;
use App\Models\Evidence;
use App\Models\EvidenceReuse;
use App\Models\ManagementSystem;
use App\Models\RequirementAssessment;
use App\Models\RequirementMapping;
use App\Models\StatementOfApplicability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrossStandardCoverageService
{
    public function build(ManagementSystem $targetSystem): array
    {
        $targetSystem->loadMissing(['standard', 'scope']);
        $requirementIds = RequirementAssessment::query()->where('management_system_id', $targetSystem->id)->pluck('requirement_id')->all();
        $controlIds = StatementOfApplicability::query()->where('management_system_id', $targetSystem->id)->where('applicable', true)->pluck('control_id')->all();
        $candidates = collect();

        if ($requirementIds !== []) {
            RequirementMapping::query()->approved()
                ->where(function (Builder $query) use ($requirementIds): void {
                    $query->whereIn('requirement_a_id', $requirementIds)->orWhereIn('requirement_b_id', $requirementIds);
                })
                ->with(['requirementA.standard','requirementA.controls','requirementB.standard','requirementB.controls'])
                ->get()
                ->each(function (RequirementMapping $mapping) use ($targetSystem, $requirementIds, $candidates): void {
                    $target = in_array($mapping->requirement_a_id, $requirementIds, true) ? $mapping->requirementA : $mapping->requirementB;
                    $source = $target->id === $mapping->requirement_a_id ? $mapping->requirementB : $mapping->requirementA;
                    foreach ($this->sourceSystems($targetSystem, $source->standard_id) as $sourceSystem) {
                        $query = $this->freshEvidence($sourceSystem->id)->where(function (Builder $query) use ($source): void {
                            $query->whereHas('requirements', fn (Builder $query) => $query->where('requirements.id', $source->id));
                            $controlIds = $source->controls->pluck('id')->all();
                            if ($controlIds !== []) {
                                $query->orWhereHas('controls', fn (Builder $query) => $query->whereIn('controls.id', $controlIds));
                            }
                        });
                        foreach ($query->get() as $evidence) {
                            $candidates->push($this->row('requirement', $mapping->id, $mapping->mapping_type, $mapping->coverage_percent, $sourceSystem, $source->standard?->code, $source->code, $target->code, $target->id, $evidence, $targetSystem));
                        }
                    }
                });
        }

        if ($controlIds !== []) {
            ControlMapping::query()->approved()
                ->where(function (Builder $query) use ($controlIds): void {
                    $query->whereIn('control_a_id', $controlIds)->orWhereIn('control_b_id', $controlIds);
                })
                ->with(['controlA.standard','controlB.standard'])
                ->get()
                ->each(function (ControlMapping $mapping) use ($targetSystem, $controlIds, $candidates): void {
                    $target = in_array($mapping->control_a_id, $controlIds, true) ? $mapping->controlA : $mapping->controlB;
                    $source = $target->id === $mapping->control_a_id ? $mapping->controlB : $mapping->controlA;
                    foreach ($this->sourceSystems($targetSystem, $source->standard_id) as $sourceSystem) {
                        foreach ($this->freshEvidence($sourceSystem->id)->whereHas('controls', fn (Builder $query) => $query->where('controls.id', $source->id))->get() as $evidence) {
                            $candidates->push($this->row('control', $mapping->id, $mapping->mapping_type, $mapping->coverage_percent, $sourceSystem, $source->standard?->code, $source->code, $target->code, $target->id, $evidence, $targetSystem));
                        }
                    }
                });
        }

        $candidates = $candidates->unique('key')->sortBy([['reuse_status', 'asc'], ['target_code', 'asc'], ['evidence_title', 'asc']])->values();

        return [
            'system' => $targetSystem,
            'summary' => [
                'opportunities' => $candidates->count(),
                'accepted' => $candidates->where('reuse_status', 'accepted')->count(),
                'available' => $candidates->where('reuse_status', 'available')->count(),
                'source_systems' => $candidates->pluck('source_system_id')->unique()->count(),
            ],
            'candidates' => $candidates,
        ];
    }

    private function row(string $kind, int $mappingId, string $mappingType, int $coveragePercent, ManagementSystem $sourceSystem, ?string $sourceStandard, string $sourceCode, string $targetCode, int $targetId, Evidence $evidence, ManagementSystem $targetSystem): array
    {
        $reuse = EvidenceReuse::query()
            ->where('evidence_id', $evidence->id)
            ->where('target_management_system_id', $targetSystem->id)
            ->when($kind === 'requirement', fn (Builder $query) => $query->where('target_requirement_id', $targetId)->whereNull('target_control_id'))
            ->when($kind === 'control', fn (Builder $query) => $query->where('target_control_id', $targetId)->whereNull('target_requirement_id'))
            ->latest('id')->first();

        return [
            'key' => implode(':', [$kind, $mappingId, $evidence->id, $targetSystem->id, $targetId]),
            'kind' => $kind,
            'mapping_id' => $mappingId,
            'mapping_type' => $mappingType,
            'coverage_percent' => $coveragePercent,
            'source_system_id' => $sourceSystem->id,
            'source_system' => $sourceSystem->code,
            'source_standard' => $sourceStandard,
            'source_code' => $sourceCode,
            'target_standard' => $targetSystem->standard?->code,
            'target_code' => $targetCode,
            'target_id' => $targetId,
            'evidence_id' => $evidence->id,
            'evidence_title' => $evidence->title,
            'captured_at' => $evidence->captured_at?->toDateString(),
            'expires_at' => $evidence->expires_at?->toDateString(),
            'reuse_status' => $reuse?->status ?? 'available',
        ];
    }

    private function sourceSystems(ManagementSystem $targetSystem, int $standardId): Collection
    {
        return ManagementSystem::query()
            ->where('compliance_scope_id', $targetSystem->compliance_scope_id)
            ->where('standard_id', $standardId)
            ->whereKeyNot($targetSystem->id)
            ->where('status', '!=', 'archived')
            ->with('standard')
            ->get();
    }

    private function freshEvidence(int $managementSystemId): Builder
    {
        return Evidence::query()
            ->where('management_system_id', $managementSystemId)
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            });
    }
}
