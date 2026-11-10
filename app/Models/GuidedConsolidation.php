<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidedConsolidation extends Model
{
    protected $attributes = ['is_current' => true, 'version' => 1];
    protected $fillable = ['guided_assessment_id', 'guided_question_id', 'context_key', 'version', 'is_current', 'method', 'summary', 'reason', 'prepared_by'];
    protected function casts(): array { return ['is_current' => 'boolean']; }
    public function assessment(): BelongsTo { return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id'); }
    public function question(): BelongsTo { return $this->belongsTo(GuidedQuestion::class, 'guided_question_id'); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by'); }
    public function sources(): BelongsToMany { return $this->belongsToMany(GuidedAnswer::class, 'guided_consolidation_sources')->withPivot(['usage', 'reason']); }
    public function decisions(): HasMany { return $this->hasMany(GuidedDecision::class); }
}
