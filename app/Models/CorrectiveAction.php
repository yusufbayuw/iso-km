<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveAction extends Model
{
    protected $fillable = ['audit_finding_id','action','owner_id','due_date','status','completed_at','verified_by','verified_at','verification_notes','effectiveness_status','effectiveness_notes'];
    protected function casts(): array { return ['due_date' => 'date','completed_at' => 'datetime','verified_at' => 'datetime']; }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
