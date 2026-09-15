<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditChecklistItem extends Model
{
    protected $fillable = ['audit_id','requirement_id','control_id','question','result','notes','evidence_id','sort_order'];
    public function audit(): BelongsTo { return $this->belongsTo(Audit::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
}
