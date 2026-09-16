<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    use AuditsChanges;

    protected $fillable = ['management_system_id','code','title','category','owner_id','target_audience','delivery_method','mandatory','recurrence_months','validity_months','status','next_review_at','description'];
    protected function casts(): array { return ['mandatory' => 'boolean','next_review_at' => 'date']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class); }
    public function assignments(): HasMany { return $this->hasMany(TrainingAssignment::class); }
}
