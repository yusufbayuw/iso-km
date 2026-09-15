<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComplianceObligation extends Model
{
    protected $fillable = ['code','title','type','jurisdiction','reference','source_url','owner_id','status','last_reviewed_at','next_review_at','description'];
    protected function casts(): array { return ['last_reviewed_at' => 'date','next_review_at' => 'date']; }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function managementSystems(): BelongsToMany { return $this->belongsToMany(ManagementSystem::class); }
}
