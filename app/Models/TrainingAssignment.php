<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAssignment extends Model
{
    use AuditsChanges;

    protected $fillable = ['training_program_id','user_id','cycle_key','assigned_at','due_at','completed_at','valid_until','status','result','score','evidence_id','notes'];
    protected function casts(): array { return ['assigned_at' => 'date','due_at' => 'date','completed_at' => 'datetime','valid_until' => 'date','score' => 'decimal:2']; }
    public function program(): BelongsTo { return $this->belongsTo(TrainingProgram::class, 'training_program_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
}
