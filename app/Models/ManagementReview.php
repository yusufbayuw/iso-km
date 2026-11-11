<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use App\Services\ManagementReviewContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementReview extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected static function booted(): void
    {
        static::creating(function (self $review): void {
            // The user can override this field with a custom summary. Never
            // overwrite an existing text or replace the official human decision.
            if ($review->management_system_id && blank($review->inputs_summary)) {
                $review->inputs_summary = app(ManagementReviewContextService::class)
                    ->summary($review->management_system_id);
            }
        });
    }

    protected $fillable = ['management_system_id','code','title','status','chairperson_id','scheduled_at','held_at','inputs_summary','outputs_summary','decisions'];
    protected function casts(): array { return ['scheduled_at' => 'datetime','held_at' => 'datetime']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function chairperson(): BelongsTo { return $this->belongsTo(User::class, 'chairperson_id'); }
    public function attendees(): BelongsToMany { return $this->belongsToMany(User::class); }
    public function actions(): HasMany { return $this->hasMany(ManagementReviewAction::class); }
}
