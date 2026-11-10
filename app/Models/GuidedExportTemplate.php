<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class GuidedExportTemplate extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if (! ($user = auth()->user())) { return; }
            app(\App\Services\GuidedFeatureGate::class)->assertEnabled();
            $access = app(\App\Services\GuidedSystemAccessService::class);
            if ($template->exists) {
                // Never let forged input move a record from an inaccessible system.
                $original = new self(['management_system_id' => $template->getOriginal('management_system_id')]);
                $access->assertCanManageTemplate($user, $original);
            }
            $access->assertCanManageTemplate($user, $template);
            if ($template->exists && $template->isDirty('management_system_id')
                && $template->exports()->exists()) {
                throw ValidationException::withMessages([
                    'management_system_id' => 'Template yang pernah diekspor tidak boleh dipindahkan.',
                ]);
            }
            if (! $template->exists) { $template->created_by = $user->id; }
        });
        static::deleting(function (self $template): void {
            if ($user = auth()->user()) {
                app(\App\Services\GuidedFeatureGate::class)->assertEnabled();
                app(\App\Services\GuidedSystemAccessService::class)
                    ->assertCanManageTemplate($user, $template);
            }
            if ($template->exports()->exists()) {
                throw ValidationException::withMessages(['template' => 'Template dengan riwayat ekspor tidak dapat dihapus.']);
            }
        });
    }

    protected $attributes = ['is_active' => true, 'report_type' => 'custom'];
    protected $fillable = ['management_system_id', 'name', 'report_type', 'file_path', 'mappings', 'is_active', 'created_by'];
    protected function casts(): array { return ['mappings' => 'array', 'is_active' => 'boolean']; }
    public function exports(): HasMany { return $this->hasMany(GuidedExportRecord::class, 'guided_export_template_id'); }
    public function system(): BelongsTo { return $this->belongsTo(ManagementSystem::class, 'management_system_id'); }
}
