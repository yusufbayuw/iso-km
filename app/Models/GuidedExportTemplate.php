<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedExportTemplate extends Model
{
    protected $attributes = ['is_active' => true];
    protected $fillable = ['management_system_id', 'name', 'file_path', 'mappings', 'is_active', 'created_by'];
    protected function casts(): array { return ['mappings' => 'array', 'is_active' => 'boolean']; }
    public function system(): BelongsTo { return $this->belongsTo(ManagementSystem::class, 'management_system_id'); }
}
