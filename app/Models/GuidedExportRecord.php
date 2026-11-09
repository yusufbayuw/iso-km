<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedExportRecord extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'guided_assessment_id', 'guided_export_template_id', 'generated_by',
        'is_final', 'mapping_snapshot', 'template_sha256', 'output_sha256', 'file_path',
    ];
    protected function casts(): array { return ['is_final' => 'boolean', 'mapping_snapshot' => 'array']; }
    public function assessment(): BelongsTo { return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id'); }
    public function template(): BelongsTo { return $this->belongsTo(GuidedExportTemplate::class, 'guided_export_template_id'); }
}
