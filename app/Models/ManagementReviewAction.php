<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementReviewAction extends Model
{
    use AuditsChanges;

    protected $fillable = ['management_review_id','action','owner_id','due_date','status','completed_at','notes'];
    protected function casts(): array { return ['due_date' => 'date','completed_at' => 'datetime']; }
    public function managementReview(): BelongsTo { return $this->belongsTo(ManagementReview::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
}
