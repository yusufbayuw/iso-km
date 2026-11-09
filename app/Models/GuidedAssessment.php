<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidedAssessment extends Model
{
    protected $fillable = ['management_system_id', 'guided_question_bank_id', 'name', 'status', 'created_by', 'high_risk_threshold', 'critical_risk_threshold', 'started_at', 'locked_at'];
    protected function casts(): array { return ['started_at' => 'datetime', 'locked_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::created(function (self $assessment): void {
            if ($assessment->created_by) {
                $assessment->memberships()->firstOrCreate(['user_id' => $assessment->created_by, 'role' => 'coordinator']);
            }
        });
    }

    public function system(): BelongsTo { return $this->belongsTo(ManagementSystem::class, 'management_system_id'); }
    public function bank(): BelongsTo { return $this->belongsTo(GuidedQuestionBank::class, 'guided_question_bank_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function memberships(): HasMany { return $this->hasMany(GuidedAssessmentMembership::class); }
    public function assignments(): HasMany { return $this->hasMany(GuidedAssignment::class); }
    public function answers(): HasMany { return $this->hasMany(GuidedAnswer::class); }
    public function consolidations(): HasMany { return $this->hasMany(GuidedConsolidation::class); }
    public function isMember(User $user, array $roles = []): bool
    {
        return $this->memberships()->where('user_id', $user->id)->when($roles !== [], fn ($q) => $q->whereIn('role', $roles))->exists();
    }
}
