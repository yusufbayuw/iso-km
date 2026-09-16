<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAssignment extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'training_program_id','user_id','cycle_key','assigned_at','due_at','started_at','submitted_at',
        'completed_at','valid_until','status','result','score','evidence_id','verified_by','verified_at',
        'verification_notes','notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date','due_at' => 'date','started_at' => 'datetime','submitted_at' => 'datetime',
            'completed_at' => 'datetime','valid_until' => 'date','score' => 'decimal:2','verified_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo { return $this->belongsTo(TrainingProgram::class, 'training_program_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
