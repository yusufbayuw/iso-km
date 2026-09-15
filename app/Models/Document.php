<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use AuditsChanges;

    protected $fillable = ['code', 'title', 'type', 'owner_id', 'confidentiality', 'status', 'review_frequency_months', 'next_review_at', 'description'];
    protected function casts(): array { return ['next_review_at' => 'date']; }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function revisions(): HasMany { return $this->hasMany(DocumentRevision::class)->latest('created_at'); }
    public function currentRevision(): HasOne { return $this->hasOne(DocumentRevision::class)->where('is_current', true); }
    public function distributions(): HasMany { return $this->hasMany(DocumentDistribution::class); }
    public function managementSystems(): BelongsToMany { return $this->belongsToMany(ManagementSystem::class); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class); }
    public function evidences(): BelongsToMany { return $this->belongsToMany(Evidence::class, 'document_evidence'); }
}
