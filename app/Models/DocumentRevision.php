<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'document_id', 'revision', 'status', 'file_path', 'change_summary',
        'prepared_by', 'reviewer_id', 'approver_id', 'submitted_for_review_at',
        'reviewed_by', 'reviewed_at', 'rejected_at', 'review_notes',
        'approved_by', 'approved_at', 'effective_at', 'obsolete_at', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'submitted_for_review_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'approved_at' => 'datetime',
            'effective_at' => 'date',
            'obsolete_at' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function preparer(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
