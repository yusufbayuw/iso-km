<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAssessment extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected $fillable = [
        'supplier_id','management_system_id','requested_by','requested_at','assessed_by','assessed_at','submitted_at',
        'reviewed_by','reviewed_at','review_notes','decided_by','decided_at','next_review_at','score','risk_rating',
        'status','evidence_id','summary','required_actions','treatment_verified_by','treatment_verified_at',
        'treatment_verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime','assessed_at' => 'date','submitted_at' => 'datetime','reviewed_at' => 'datetime',
            'decided_at' => 'datetime','next_review_at' => 'date','score' => 'decimal:2','treatment_verified_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessed_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
    public function treatmentVerifier(): BelongsTo { return $this->belongsTo(User::class, 'treatment_verified_by'); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
}
