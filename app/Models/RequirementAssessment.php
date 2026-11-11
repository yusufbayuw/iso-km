<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use App\Services\ManagementSystemIntegrityService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementAssessment extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected static function booted(): void
    {
        static::saving(fn (self $record) => app(ManagementSystemIntegrityService::class)
            ->assertCatalogItem($record->management_system_id, $record->requirement_id, 'requirement'));
    }

    protected $fillable = ['management_system_id', 'requirement_id', 'status', 'owner_id', 'notes', 'last_assessed_at', 'next_review_at'];
    protected function casts(): array { return ['last_assessed_at' => 'datetime', 'next_review_at' => 'date']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
}
