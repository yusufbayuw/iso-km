<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class GuidedAssessment extends Model
{
    protected $attributes = ['status' => 'draft', 'high_risk_threshold' => 12, 'critical_risk_threshold' => 20];
    protected $fillable = ['management_system_id', 'guided_question_bank_id', 'name', 'status', 'created_by', 'high_risk_threshold', 'critical_risk_threshold', 'started_at', 'locked_at'];
    protected function casts(): array { return ['started_at' => 'datetime', 'locked_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if ($actor = auth()->user()) {
                app(\App\Services\GuidedFeatureGate::class)->assertEnabled();
                if (! $actor->can('guided.assessment.manage')) {
                    throw ValidationException::withMessages(['authorization' => 'Tidak diizinkan membuat periode asesmen.']);
                }
                app(\App\Services\GuidedSystemAccessService::class)
                    ->assertCanAccess($actor, (int) $run->management_system_id);
                $run->created_by = $actor->id;
            }
        });
        static::saving(function (self $run): void {
            if ($run->exists && ($run->isDirty('management_system_id') || $run->isDirty('guided_question_bank_id') || $run->isDirty('created_by'))) {
                throw ValidationException::withMessages(['assessment' => 'Identitas, bank dan pemilik periode tidak dapat diganti setelah dibuat.']);
            }
            if ($run->exists && $run->getOriginal('locked_at') && $run->isDirty()) {
                throw ValidationException::withMessages(['assessment' => 'Asesmen final yang terkunci tidak boleh diubah.']);
            }
            if ($run->exists && ($run->isDirty('high_risk_threshold') || $run->isDirty('critical_risk_threshold'))
                && ($run->assignments()->exists() || $run->consolidations()->exists())) {
                throw ValidationException::withMessages(['risk' => 'Ambang risiko tidak boleh diubah setelah wawancara dimulai.']);
            }
            $bank = $run->bank()->first();
            $system = $run->system()->first();
            if (! $bank || ! $system || $bank->standard_id !== $system->standard_id || ($run->isDirty('guided_question_bank_id') && $bank->status !== 'published')) {
                throw ValidationException::withMessages(['guided_question_bank_id' => 'Bank harus terbit dan sesuai standar sistem manajemen.']);
            }
            if ($run->high_risk_threshold < 1 || $run->high_risk_threshold > 25 || $run->critical_risk_threshold < $run->high_risk_threshold || $run->critical_risk_threshold > 25) {
                throw ValidationException::withMessages(['risk' => 'Ambang risiko harus 1-25 dan ambang kritis tidak lebih rendah daripada ambang tinggi.']);
            }
        });
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
    public function scopeItems(): HasMany { return $this->hasMany(GuidedScopeItem::class); }
    public function consolidations(): HasMany { return $this->hasMany(GuidedConsolidation::class); }
    public function isMember(User $user, array $roles = []): bool
    {
        return $this->memberships()->where('user_id', $user->id)->when($roles !== [], fn ($q) => $q->whereIn('role', $roles))->exists();
    }
}
