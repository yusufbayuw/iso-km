<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ControlMapping extends Model
{
    use AuditsChanges;

    protected $fillable = ['control_a_id','control_b_id','mapping_type','coverage_percent','rationale','status','mapped_by','reviewed_by','reviewed_at'];
    protected function casts(): array { return ['coverage_percent' => 'integer', 'reviewed_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::saving(function (ControlMapping $mapping): void {
            if (! $mapping->control_a_id || ! $mapping->control_b_id) { return; }
            if ($mapping->control_a_id === $mapping->control_b_id) {
                throw ValidationException::withMessages(['control_b_id' => 'Control yang dipetakan harus berbeda.']);
            }
            if ($mapping->control_a_id > $mapping->control_b_id) {
                [$mapping->control_a_id, $mapping->control_b_id] = [$mapping->control_b_id, $mapping->control_a_id];
            }
            $standardIds = Control::query()->whereKey([$mapping->control_a_id, $mapping->control_b_id])->pluck('standard_id')->unique();
            if ($standardIds->count() !== 2) {
                throw ValidationException::withMessages(['control_b_id' => 'Cross-standard mapping harus menghubungkan control dari dua standar yang berbeda.']);
            }
            $mapping->mapped_by ??= auth()->id();
        });
    }

    public function scopeApproved(Builder $query): Builder { return $query->where('status', 'approved'); }
    public function controlA(): BelongsTo { return $this->belongsTo(Control::class, 'control_a_id'); }
    public function controlB(): BelongsTo { return $this->belongsTo(Control::class, 'control_b_id'); }
    public function mapper(): BelongsTo { return $this->belongsTo(User::class, 'mapped_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
