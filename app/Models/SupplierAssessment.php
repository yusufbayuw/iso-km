<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAssessment extends Model
{
    use AuditsChanges;

    protected $fillable = ['supplier_id','management_system_id','assessed_by','assessed_at','next_review_at','score','risk_rating','status','evidence_id','summary','required_actions'];
    protected function casts(): array { return ['assessed_at' => 'date','next_review_at' => 'date','score' => 'decimal:2']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessed_by'); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
}
