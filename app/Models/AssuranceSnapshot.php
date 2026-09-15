<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssuranceSnapshot extends Model
{
    protected $fillable = ['management_system_id', 'requirement_readiness', 'control_implementation', 'evidence_coverage', 'evidence_freshness', 'risk_health', 'findings_health', 'action_health', 'confidence_level', 'attention_counts', 'calculated_at'];
    protected function casts(): array { return ['requirement_readiness' => 'decimal:2', 'control_implementation' => 'decimal:2', 'evidence_coverage' => 'decimal:2', 'evidence_freshness' => 'decimal:2', 'risk_health' => 'decimal:2', 'findings_health' => 'decimal:2', 'action_health' => 'decimal:2', 'attention_counts' => 'array', 'calculated_at' => 'datetime']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
}
