<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvidenceSchedule extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected $fillable = ['management_system_id', 'owner_id', 'code', 'title', 'evidence_type', 'frequency', 'interval_days', 'lead_days', 'validity_days', 'next_due_at', 'is_active', 'description'];
    protected function casts(): array { return ['next_due_at' => 'date', 'is_active' => 'boolean']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class, 'evidence_schedule_requirement'); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class, 'control_evidence_schedule'); }
    public function requests(): HasMany { return $this->hasMany(EvidenceRequest::class); }
}
