<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use AuditsChanges;

    protected $fillable = ['code','name','category','service_description','owner_id','criticality','status','contact_name','contact_email','next_review_at','notes'];
    protected function casts(): array { return ['next_review_at' => 'date']; }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function managementSystems(): BelongsToMany { return $this->belongsToMany(ManagementSystem::class); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class); }
    public function evidences(): BelongsToMany { return $this->belongsToMany(Evidence::class); }
    public function assessments(): HasMany { return $this->hasMany(SupplierAssessment::class); }
}
