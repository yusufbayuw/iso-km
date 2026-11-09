<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class GuidedQuestion extends Model
{
    protected static function booted(): void
    {
        $guard = static function (self $question): void {
            if ($question->bank()->first()?->status !== 'draft') {
                throw ValidationException::withMessages(['question' => 'Pertanyaan pada bank terbit tidak dapat diubah. Buat versi bank baru.']);
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    protected $fillable = ['guided_question_bank_id', 'code', 'prompt', 'help_text', 'answer_type', 'answer_options', 'branch_rules', 'is_required', 'sort_order'];
    protected function casts(): array { return ['answer_options' => 'array', 'branch_rules' => 'array', 'is_required' => 'boolean']; }
    public function bank(): BelongsTo { return $this->belongsTo(GuidedQuestionBank::class, 'guided_question_bank_id'); }
    public function targets(): HasMany { return $this->hasMany(GuidedQuestionTarget::class); }
    public function assignments(): HasMany { return $this->hasMany(GuidedAssignment::class); }
    public function answers(): HasMany { return $this->hasMany(GuidedAnswer::class); }
}
