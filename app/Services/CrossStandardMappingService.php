<?php

namespace App\Services;

use App\Models\Control;
use App\Models\ControlMapping;
use App\Models\Evidence;
use App\Models\EvidenceReuse;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use App\Models\RequirementMapping;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CrossStandardMappingService
{
    public function approveRequirement(RequirementMapping $mapping, User $actor): RequirementMapping
    {
        $this->authorize($actor, 'cross_standard_mapping.approve');
        $this->assertMappingStatus($mapping->status, ['draft', 'rejected']);
        $mapping->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        return $mapping->refresh();
    }

    public function rejectRequirement(RequirementMapping $mapping, User $actor): RequirementMapping
    {
        $this->authorize($actor, 'cross_standard_mapping.approve');
        $this->assertMappingStatus($mapping->status, ['draft', 'approved']);
        $mapping->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        return $mapping->refresh();
    }

    public function approveControl(ControlMapping $mapping, User $actor): ControlMapping
    {
        $this->authorize($actor, 'cross_standard_mapping.approve');
        $this->assertMappingStatus($mapping->status, ['draft', 'rejected']);
        $mapping->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        return $mapping->refresh();
    }

    public function rejectControl(ControlMapping $mapping, User $actor): ControlMapping
    {
        $this->authorize($actor, 'cross_standard_mapping.approve');
        $this->assertMappingStatus($mapping->status, ['draft', 'approved']);
        $mapping->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        return $mapping->refresh();
    }

    public function acceptRequirementReuse(Evidence $evidence, ManagementSystem $targetSystem, Requirement $target, RequirementMapping $mapping, User $actor): EvidenceReuse
    {
        $this->authorize($actor, 'cross_standard_reuse.accept');
        $this->assertApproved($mapping->status);
        $source = $this->otherRequirement($mapping, $target);
        $this->assertTargetStandard($targetSystem, $target->standard_id);
        $this->assertEvidenceSource($evidence, $targetSystem, $source->standard_id);

        $controlIds = $source->controls()->pluck('controls.id');
        $linked = $evidence->requirements()->where('requirements.id', $source->id)->exists()
            || ($controlIds->isNotEmpty() && $evidence->controls()->whereIn('controls.id', $controlIds)->exists());
        if (! $linked) {
            throw ValidationException::withMessages(['evidence_id' => 'Evidence tidak terhubung ke requirement sumber atau control terkait.']);
        }

        return EvidenceReuse::query()->updateOrCreate([
            'evidence_id' => $evidence->id,
            'target_management_system_id' => $targetSystem->id,
            'target_requirement_id' => $target->id,
            'target_control_id' => null,
        ], [
            'requirement_mapping_id' => $mapping->id,
            'control_mapping_id' => null,
            'status' => 'accepted',
            'proposed_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    public function acceptControlReuse(Evidence $evidence, ManagementSystem $targetSystem, Control $target, ControlMapping $mapping, User $actor): EvidenceReuse
    {
        $this->authorize($actor, 'cross_standard_reuse.accept');
        $this->assertApproved($mapping->status);
        $source = $this->otherControl($mapping, $target);
        $this->assertTargetStandard($targetSystem, $target->standard_id);
        $this->assertEvidenceSource($evidence, $targetSystem, $source->standard_id);

        if (! $evidence->controls()->where('controls.id', $source->id)->exists()) {
            throw ValidationException::withMessages(['evidence_id' => 'Evidence tidak terhubung ke control sumber.']);
        }

        return EvidenceReuse::query()->updateOrCreate([
            'evidence_id' => $evidence->id,
            'target_management_system_id' => $targetSystem->id,
            'target_requirement_id' => null,
            'target_control_id' => $target->id,
        ], [
            'requirement_mapping_id' => null,
            'control_mapping_id' => $mapping->id,
            'status' => 'accepted',
            'proposed_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    public function rejectReuse(EvidenceReuse $reuse, User $actor, ?string $notes = null): EvidenceReuse
    {
        $this->authorize($actor, 'cross_standard_reuse.accept');
        $reuse->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'notes' => $notes]);
        return $reuse->refresh();
    }

    private function otherRequirement(RequirementMapping $mapping, Requirement $target): Requirement
    {
        if ($mapping->requirement_a_id === $target->id) { return $mapping->requirementB; }
        if ($mapping->requirement_b_id === $target->id) { return $mapping->requirementA; }
        throw ValidationException::withMessages(['target_requirement_id' => 'Requirement target bukan bagian dari mapping ini.']);
    }

    private function otherControl(ControlMapping $mapping, Control $target): Control
    {
        if ($mapping->control_a_id === $target->id) { return $mapping->controlB; }
        if ($mapping->control_b_id === $target->id) { return $mapping->controlA; }
        throw ValidationException::withMessages(['target_control_id' => 'Control target bukan bagian dari mapping ini.']);
    }

    private function assertEvidenceSource(Evidence $evidence, ManagementSystem $targetSystem, int $sourceStandardId): void
    {
        $sourceSystem = $evidence->managementSystem;
        if (! $sourceSystem || $sourceSystem->standard_id !== $sourceStandardId || $sourceSystem->compliance_scope_id !== $targetSystem->compliance_scope_id || $sourceSystem->id === $targetSystem->id) {
            throw ValidationException::withMessages(['evidence_id' => 'Evidence harus berasal dari management system standar lain pada compliance scope yang sama.']);
        }
        if ($evidence->status !== 'active' || $evidence->verification_status !== 'verified' || ($evidence->expires_at && $evidence->expires_at->lt(today()))) {
            throw ValidationException::withMessages(['evidence_id' => 'Hanya evidence active, verified, dan belum expired yang dapat direuse.']);
        }
    }

    private function assertTargetStandard(ManagementSystem $system, int $standardId): void
    {
        if ($system->standard_id !== $standardId) {
            throw ValidationException::withMessages(['target_management_system_id' => 'Target tidak sesuai dengan standar management system.']);
        }
    }

    private function assertApproved(string $status): void
    {
        if ($status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Evidence hanya dapat direuse melalui mapping yang sudah approved.']);
        }
    }

    private function assertMappingStatus(string $status, array $allowed): void
    {
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Transisi mapping tidak valid dari status {$status}."]);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Permission {$permission} diperlukan.");
        }
    }
}
