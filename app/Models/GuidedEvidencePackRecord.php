<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedEvidencePackRecord extends Model
{
    protected $fillable = [
        'guided_assessment_id', 'generated_by', 'includes_sensitive',
        'included_count', 'omitted_count', 'file_path', 'sha256',
    ];
    protected function casts(): array { return ['includes_sensitive' => 'boolean']; }
    public function assessment(): BelongsTo { return $this->belongsTo(GuidedAssessment::class, 'guided_assessment_id'); }
}
