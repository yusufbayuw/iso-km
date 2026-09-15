<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ActionItem extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ActionItem $item): void {
            $item->action_key ??= 'manual:'.Str::uuid();
        });
    }

    protected $fillable = [
        'management_system_id', 'owner_id', 'source_type', 'source_id', 'action_key',
        'category', 'title', 'description', 'priority', 'status', 'due_at',
        'completed_at', 'is_generated', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_generated' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function source(): MorphTo { return $this->morphTo(); }
}
