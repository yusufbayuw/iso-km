<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedAnswerDraft extends Model
{
    protected $fillable = [
        'guided_assessment_id', 'guided_question_id', 'guided_assignment_id',
        'respondent_id', 'context_key', 'response', 'notes', 'evidence_ids', 'version',
    ];

    protected function casts(): array
    {
        return ['response' => 'array', 'evidence_ids' => 'array', 'version' => 'integer'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(GuidedAssignment::class, 'guided_assignment_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(GuidedQuestion::class, 'guided_question_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }
}
