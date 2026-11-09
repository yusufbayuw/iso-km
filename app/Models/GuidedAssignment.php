<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidedAssignment extends Model
{
    protected $attributes = ['status' => 'assigned'];
    protected $fillable = ['guided_assessment_id', 'guided_question_id', 'user_id', 'status', 'due_at'];
    protected function casts(): array { return ['due_at' => 'date']; }
    public function assessment(): BelongsTo { return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id'); }
    public function question(): BelongsTo { return $this->belongsTo(GuidedQuestion::class, 'guided_question_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function answers(): HasMany { return $this->hasMany(GuidedAnswer::class); }
}
