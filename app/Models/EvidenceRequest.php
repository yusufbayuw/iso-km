<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceRequest extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected $fillable = ['evidence_schedule_id', 'management_system_id', 'owner_id', 'evidence_id', 'verified_by', 'due_at', 'period_start', 'period_end', 'period_label', 'status', 'requested_at', 'submitted_at', 'verified_at', 'verification_notes'];
    protected function casts(): array { return ['due_at' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'requested_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime']; }
    public function schedule(): BelongsTo { return $this->belongsTo(EvidenceSchedule::class, 'evidence_schedule_id'); }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
