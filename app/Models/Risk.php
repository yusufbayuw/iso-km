<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Risk extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected $fillable = ['management_system_id', 'code', 'title', 'description', 'category', 'owner_id', 'likelihood', 'impact', 'treatment_strategy', 'treatment_plan', 'residual_likelihood', 'residual_impact', 'status', 'target_date'];
    protected function casts(): array { return ['target_date' => 'date']; }
    protected static function booted(): void
    {
        static::saving(function (Risk $risk): void {
            $risk->inherent_score = max(1, (int) $risk->likelihood) * max(1, (int) $risk->impact);
            $risk->residual_score = $risk->residual_likelihood !== null && $risk->residual_impact !== null
                ? max(1, (int) $risk->residual_likelihood) * max(1, (int) $risk->residual_impact)
                : null;
        });
    }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class, 'requirement_risk'); }
}
