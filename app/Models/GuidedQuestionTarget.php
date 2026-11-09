<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class GuidedQuestionTarget extends Model
{
    protected $attributes = ['is_required' => true, 'evidence_profile' => 'standard'];
    protected $fillable = ['guided_question_id', 'requirement_id', 'control_id', 'criterion', 'evidence_profile', 'evidence_hints', 'is_required'];
    protected function casts(): array { return ['evidence_hints' => 'array', 'is_required' => 'boolean']; }

    protected static function booted(): void
    {
        static::saving(function (self $target): void {
            if (($target->requirement_id === null) === ($target->control_id === null)) {
                throw ValidationException::withMessages(['target' => 'Pilih tepat satu requirement atau control.']);
            }
            $standardId = $target->question()->with('bank')->first()?->bank?->standard_id;
            $refStandardId = $target->requirement_id
                ? Requirement::query()->whereKey($target->requirement_id)->value('standard_id')
                : Control::query()->whereKey($target->control_id)->value('standard_id');
            if ($standardId === null || (int) $standardId !== (int) $refStandardId) {
                throw ValidationException::withMessages(['target' => 'Target harus berasal dari standard yang sama dengan bank pertanyaan.']);
            }
        });
    }

    public function question(): BelongsTo { return $this->belongsTo(GuidedQuestion::class, 'guided_question_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
}
