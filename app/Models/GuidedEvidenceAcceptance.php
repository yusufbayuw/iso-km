<?php
namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedEvidenceAcceptance extends Model
{
    use AuditsChanges;
    protected $fillable = ['guided_decision_id', 'evidence_id', 'status', 'rationale', 'reviewed_by', 'reviewed_at'];
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
    public function decision(): BelongsTo { return $this->belongsTo(GuidedDecision::class, 'guided_decision_id'); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
}
