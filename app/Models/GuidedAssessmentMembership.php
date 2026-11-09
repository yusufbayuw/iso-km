<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedAssessmentMembership extends Model
{
    protected $fillable = ['guided_assessment_id', 'user_id', 'role'];
    public function assessment(): BelongsTo { return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
