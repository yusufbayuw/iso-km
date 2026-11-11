<?php

namespace App\Services;

use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;

class AuditFindingAutomationService
{
    public function fromChecklist(AuditChecklistItem $item): ?AuditFinding
    {
        $kind = match ($item->result) {
            'nonconformity' => 'nonconformity',
            'observation' => 'observation',
            'opportunity' => 'opportunity',
            default => null,
        };

        if (! $kind || ! $item->audit_id || ! $item->exists) {
            return null;
        }

        // The unique foreign key makes repeated checklist saves idempotent.
        // Never rewrite a finding once a person starts its assessment.
        return AuditFinding::query()->firstOrCreate(
            ['audit_checklist_item_id' => $item->id],
            [
                'audit_id' => $item->audit_id,
                'code' => 'SYS-AUD-'.$item->audit_id.'-CHK-'.$item->id,
                'finding_type' => $kind,
                'title' => 'Hasil checklist: '.mb_substr($item->question, 0, 180),
                'description' => filled($item->notes) ? $item->notes : $item->question,
                'requirement_id' => $item->requirement_id,
                'control_id' => $item->control_id,
                'status' => 'open',
            ],
        );
    }
}
