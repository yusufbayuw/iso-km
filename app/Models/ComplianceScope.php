<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceScope extends Model
{
    protected $fillable = ['code', 'name', 'description', 'owner_id', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function managementSystems(): HasMany { return $this->hasMany(ManagementSystem::class); }
}
