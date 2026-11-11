<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\ManagementSystem;
use App\Models\Requirement;
use Illuminate\Validation\ValidationException;

class ManagementSystemIntegrityService
{
    public function standardId(?int $systemId): ?int
    {
        return $systemId ? ManagementSystem::query()->whereKey($systemId)->value('standard_id') : null;
    }

    public function auditSystemId(?int $auditId): ?int
    {
        return $auditId ? Audit::query()->whereKey($auditId)->value('management_system_id') : null;
    }

    public function assertCatalogItem(?int $systemId, ?int $itemId, string $kind): void
    {
        if (! $systemId || ! $itemId) {
            return;
        }

        $model = match ($kind) {
            'requirement' => Requirement::class,
            'control' => Control::class,
            default => throw new \InvalidArgumentException('Unsupported catalog item kind.'),
        };

        $standardId = $this->standardId($systemId);
        $actualStandardId = $model::query()->whereKey($itemId)->value('standard_id');

        if ($standardId === null || $actualStandardId === null || (int) $actualStandardId !== (int) $standardId) {
            throw ValidationException::withMessages([
                "{$kind}_id" => 'Item berasal dari standar berbeda dengan sistem manajemen yang dipilih.',
            ]);
        }
    }

    public function assertEvidence(?int $systemId, ?int $evidenceId): void
    {
        if (! $systemId || ! $evidenceId) {
            return;
        }

        $sourceSystemId = Evidence::query()->whereKey($evidenceId)->value('management_system_id');
        if ($sourceSystemId === null || (int) $sourceSystemId !== (int) $systemId) {
            throw ValidationException::withMessages([
                'evidence_id' => 'Evidence harus berasal dari sistem manajemen yang sama. Gunakan evidence reuse untuk lintas standar.',
            ]);
        }
    }
}
