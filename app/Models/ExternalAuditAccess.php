<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalAuditAccess extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected $fillable = [
        'user_id', 'management_system_id', 'granted_by', 'valid_from', 'expires_at',
        'allow_audit_pack', 'status', 'notes', 'revoked_by', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'expires_at' => 'datetime',
            'allow_audit_pack' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExternalAuditAccess $access): void {
            $access->granted_by ??= auth()->id();
        });
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function getAccessStateAttribute(): string
    {
        if ($this->status === 'revoked' || $this->revoked_at) {
            return 'revoked';
        }
        if ($this->valid_from && $this->valid_from->isFuture()) {
            return 'scheduled';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }
        return 'active';
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function grantor(): BelongsTo { return $this->belongsTo(User::class, 'granted_by'); }
    public function revoker(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by'); }
}
