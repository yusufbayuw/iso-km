<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditFinding extends Model
{
    protected $fillable = ['audit_id','code','finding_type','severity','title','description','requirement_id','control_id','owner_id','due_date','status','root_cause','correction'];
    protected function casts(): array { return ['due_date' => 'date']; }
    public function audit(): BelongsTo { return $this->belongsTo(Audit::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function correctiveActions(): HasMany { return $this->hasMany(CorrectiveAction::class); }
}
