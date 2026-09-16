<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class RequirementMapping extends Model
{
    use AuditsChanges;

    protected $fillable = ['requirement_a_id','requirement_b_id','mapping_type','coverage_percent','rationale','status','mapped_by','reviewed_by','reviewed_at'];

    protected $attributes = [
        'mapping_type' => 'related',
        'coverage_percent' => 50,
        'status' => 'draft',
    ];

    protected function casts(): array { return ['coverage_percent' => 'integer', 'reviewed_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::saving(function (RequirementMapping $mapping): void {
            if (! $mapping->requirement_a_id || ! $mapping->requirement_b_id) { return; }
            if ($mapping->requirement_a_id === $mapping->requirement_b_id) {
                throw ValidationException::withMessages(['requirement_b_id' => 'Requirement yang dipetakan harus berbeda.']);
            }
            if ($mapping->requirement_a_id > $mapping->requirement_b_id) {
                [$mapping->requirement_a_id, $mapping->requirement_b_id] = [$mapping->requirement_b_id, $mapping->requirement_a_id];
            }
            $standardIds = Requirement::query()->whereKey([$mapping->requirement_a_id, $mapping->requirement_b_id])->pluck('standard_id')->unique();
            if ($standardIds->count() !== 2) {
                throw ValidationException::withMessages(['requirement_b_id' => 'Cross-standard mapping harus menghubungkan requirement dari dua standar yang berbeda.']);
            }
            $mapping->mapped_by ??= auth()->id();
        });
    }

    public function scopeApproved(Builder $query): Builder { return $query->where('status', 'approved'); }
    public function requirementA(): BelongsTo { return $this->belongsTo(Requirement::class, 'requirement_a_id'); }
    public function requirementB(): BelongsTo { return $this->belongsTo(Requirement::class, 'requirement_b_id'); }
    public function mapper(): BelongsTo { return $this->belongsTo(User::class, 'mapped_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
