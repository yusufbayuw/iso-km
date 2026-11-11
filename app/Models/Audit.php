<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audit extends Model
{
    use AuditsChanges;

    protected static function booted(): void
    {
        static::saving(function (self $audit): void {
            if ($audit->exists && $audit->isDirty('status') && $audit->status === 'completed') {
                if ($audit->checklistItems()->where('result', 'not_reviewed')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Masih ada checklist audit yang belum dinilai.',
                    ]);
                }
                $audit->actual_end_at ??= now();
            }
            if ($audit->exists && $audit->isDirty('status') && $audit->status === 'in_progress') {
                $audit->actual_start_at ??= now();
            }
        });
    }

    protected $fillable = ['management_system_id','code','title','type','status','lead_auditor_id','planned_start_at','planned_end_at','actual_start_at','actual_end_at','scope','objectives','criteria','summary'];
    protected function casts(): array { return ['planned_start_at' => 'datetime','planned_end_at' => 'datetime','actual_start_at' => 'datetime','actual_end_at' => 'datetime']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function leadAuditor(): BelongsTo { return $this->belongsTo(User::class, 'lead_auditor_id'); }
    public function members(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot('role'); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class); }
    public function checklistItems(): HasMany { return $this->hasMany(AuditChecklistItem::class)->orderBy('sort_order'); }
    public function findings(): HasMany { return $this->hasMany(AuditFinding::class); }
}
