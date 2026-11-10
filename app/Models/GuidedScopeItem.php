<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class GuidedScopeItem extends Model
{
    protected $fillable = [
        'guided_assessment_id', 'kind', 'category', 'title', 'in_scope',
        'classification', 'related_controls', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['in_scope' => 'boolean', 'related_controls' => 'array'];
    }

    protected static function booted(): void
    {
        $guard = static function (self $item): void {
            if (! in_array($item->kind, ['asset', 'activity'], true)) {
                throw ValidationException::withMessages(['kind' => 'Jenis inventaris tidak dikenal.']);
            }
            $run = $item->assessment()->first();
            if (! $run || $run->locked_at) {
                throw ValidationException::withMessages(['assessment' => 'Inventaris tidak dapat diubah setelah asesmen dikunci.']);
            }
            if ($item->exists && $item->isDirty('guided_assessment_id')) {
                throw ValidationException::withMessages(['assessment' => 'Inventaris tidak dapat dipindahkan ke periode lain.']);
            }
            if (($user = auth()->user()) && (! $user->can('guided.assessment.manage')
                || ! $run->isMember($user, ['coordinator']))) {
                throw ValidationException::withMessages(['authorization' => 'Hanya koordinator asesmen yang boleh memperbarui inventaris.']);
            }
            if (! $item->exists && isset($user)) { $item->created_by = $user->id; }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id');
    }
}
