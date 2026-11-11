<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\ManagementSystemIntegrityService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditChecklistItem extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $integrity = app(ManagementSystemIntegrityService::class);
            $systemId = $integrity->auditSystemId($item->audit_id);
            $integrity->assertCatalogItem($systemId, $item->requirement_id, 'requirement');
            $integrity->assertCatalogItem($systemId, $item->control_id, 'control');
            $integrity->assertEvidence($systemId, $item->evidence_id);
        });
    }

    protected $fillable = ['audit_id','requirement_id','control_id','question','result','notes','evidence_id','sort_order'];
    public function audit(): BelongsTo { return $this->belongsTo(Audit::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
}
