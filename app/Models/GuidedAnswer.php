<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class GuidedAnswer extends Model
{
    public $timestamps = true;
    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['answer' => 'Jawaban bersifat immutable. Buat entri revisi baru.']));
        static::deleting(fn () => throw ValidationException::withMessages(['answer' => 'Jawaban historis tidak dapat dihapus.']));
    }
    protected $fillable = ['guided_assessment_id', 'guided_question_id', 'guided_assignment_id', 'respondent_id', 'context_key', 'response', 'notes', 'supersedes_id', 'submitted_at'];
    protected function casts(): array { return ['response' => 'array', 'submitted_at' => 'datetime']; }
    public function assessment(): BelongsTo { return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id'); }
    public function question(): BelongsTo { return $this->belongsTo(GuidedQuestion::class, 'guided_question_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(GuidedAssignment::class, 'guided_assignment_id'); }
    public function respondent(): BelongsTo { return $this->belongsTo(User::class, 'respondent_id'); }
    public function evidences(): BelongsToMany { return $this->belongsToMany(Evidence::class, 'guided_answer_evidence'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_id'); }
}
