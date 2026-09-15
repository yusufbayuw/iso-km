<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    protected $fillable = ['document_id', 'revision', 'file_path', 'change_summary', 'prepared_by', 'reviewed_by', 'approved_by', 'approved_at', 'effective_at', 'obsolete_at', 'is_current'];
    protected function casts(): array { return ['approved_at' => 'datetime', 'effective_at' => 'date', 'obsolete_at' => 'date', 'is_current' => 'boolean']; }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
}
