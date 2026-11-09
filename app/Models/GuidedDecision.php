<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidedDecision extends Model
{
    protected $attributes = ['status' => 'submitted', 'risk_score' => 1];
    protected $fillable = ['guided_consolidation_id', 'guided_question_target_id', 'proposed_status', 'applicable', 'rationale', 'risk_score', 'risk_owner_id', 'status', 'baseline_status', 'baseline_updated_at', 'approved_by', 'approved_at', 'synced_at'];
    protected function casts(): array { return ['applicable' => 'boolean', 'baseline_updated_at' => 'datetime', 'approved_at' => 'datetime', 'synced_at' => 'datetime']; }
    public function consolidation(): BelongsTo { return $this->belongsTo(GuidedConsolidation::class, 'guided_consolidation_id'); }
    public function target(): BelongsTo { return $this->belongsTo(GuidedQuestionTarget::class, 'guided_question_target_id'); }
    public function approvals(): HasMany { return $this->hasMany(GuidedApproval::class); }
    public function acceptances(): HasMany { return $this->hasMany(GuidedEvidenceAcceptance::class); }
    public function riskOwner(): BelongsTo { return $this->belongsTo(User::class, 'risk_owner_id'); }
}
