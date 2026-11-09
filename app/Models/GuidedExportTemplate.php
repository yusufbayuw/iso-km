<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidedExportTemplate extends Model
{
    protected $attributes = ['is_active' => true];
    protected $fillable = ['management_system_id', 'name', 'file_path', 'mappings', 'is_active', 'created_by'];
    protected function casts(): array { return ['mappings' => 'array', 'is_active' => 'boolean']; }
    public function exports(): HasMany { return $this->hasMany(GuidedExportRecord::class, 'guided_export_template_id'); }
    public function system(): BelongsTo { return $this->belongsTo(ManagementSystem::class, 'management_system_id'); }
}
