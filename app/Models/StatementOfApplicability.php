<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatementOfApplicability extends Model
{
    use AuditsChanges;

    protected $table = 'soa_entries';
    protected $fillable = ['management_system_id', 'control_id', 'applicable', 'justification', 'implementation_status', 'owner_id', 'implementation_notes', 'last_reviewed_at', 'next_review_at'];
    protected function casts(): array { return ['applicable' => 'boolean', 'last_reviewed_at' => 'datetime', 'next_review_at' => 'date']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
}
