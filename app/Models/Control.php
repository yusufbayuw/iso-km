<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Control extends Model
{
    protected $fillable = ['standard_id', 'parent_id', 'code', 'title', 'category', 'description', 'is_group', 'sort_order'];
    protected function casts(): array { return ['is_group' => 'boolean']; }
    public function scopeAssessable(Builder $query): Builder { return $query->where('is_group', false); }
    public function standard(): BelongsTo { return $this->belongsTo(Standard::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class); }
    public function documents(): BelongsToMany { return $this->belongsToMany(Document::class); }
    public function evidences(): BelongsToMany { return $this->belongsToMany(Evidence::class, 'control_evidence'); }
    public function risks(): BelongsToMany { return $this->belongsToMany(Risk::class); }
    public function soaEntries(): HasMany { return $this->hasMany(StatementOfApplicability::class); }
}
