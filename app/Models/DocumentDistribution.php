<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDistribution extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'document_id', 'user_id', 'document_revision_id', 'distributed_at',
        'acknowledgement_due_at', 'acknowledged_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'distributed_at' => 'datetime',
            'acknowledgement_due_at' => 'date',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function revision(): BelongsTo { return $this->belongsTo(DocumentRevision::class, 'document_revision_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
