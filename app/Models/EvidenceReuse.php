<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class EvidenceReuse extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'evidence_id','target_management_system_id','target_requirement_id','target_control_id',
        'requirement_mapping_id','control_mapping_id','status','proposed_by','reviewed_by','reviewed_at','notes',
    ];

    protected function casts(): array { return ['reviewed_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::saving(function (EvidenceReuse $reuse): void {
            $targets = collect([$reuse->target_requirement_id, $reuse->target_control_id])->filter()->count();
            if ($targets !== 1) {
                throw ValidationException::withMessages(['target_requirement_id' => 'Evidence reuse harus menargetkan tepat satu requirement atau control.']);
            }
        });
    }

    public function scopeAccepted(Builder $query): Builder { return $query->where('status', 'accepted'); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
    public function targetManagementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class, 'target_management_system_id'); }
    public function targetRequirement(): BelongsTo { return $this->belongsTo(Requirement::class, 'target_requirement_id'); }
    public function targetControl(): BelongsTo { return $this->belongsTo(Control::class, 'target_control_id'); }
    public function requirementMapping(): BelongsTo { return $this->belongsTo(RequirementMapping::class); }
    public function controlMapping(): BelongsTo { return $this->belongsTo(ControlMapping::class); }
    public function proposer(): BelongsTo { return $this->belongsTo(User::class, 'proposed_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
