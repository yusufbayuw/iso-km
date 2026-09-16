<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requirement extends Model
{
    protected $fillable = ['standard_id', 'parent_id', 'code', 'title', 'description', 'type', 'is_mandatory', 'sort_order'];
    protected function casts(): array { return ['is_mandatory' => 'boolean']; }
    public function standard(): BelongsTo { return $this->belongsTo(Standard::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class); }
    public function assessments(): HasMany { return $this->hasMany(RequirementAssessment::class); }
    public function documents(): BelongsToMany { return $this->belongsToMany(Document::class); }
    public function evidences(): BelongsToMany { return $this->belongsToMany(Evidence::class, 'evidence_requirement'); }
    public function risks(): BelongsToMany { return $this->belongsToMany(Risk::class, 'requirement_risk'); }
    public function mappingsAsA(): HasMany { return $this->hasMany(RequirementMapping::class, 'requirement_a_id'); }
    public function mappingsAsB(): HasMany { return $this->hasMany(RequirementMapping::class, 'requirement_b_id'); }
    public function evidenceReuses(): HasMany { return $this->hasMany(EvidenceReuse::class, 'target_requirement_id'); }
}
