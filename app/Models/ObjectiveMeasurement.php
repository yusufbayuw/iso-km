<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectiveMeasurement extends Model
{
    protected $fillable = ['objective_id','period_date','actual_value','status','notes','measured_by'];
    protected function casts(): array { return ['period_date' => 'date','actual_value' => 'decimal:4']; }
    public function objective(): BelongsTo { return $this->belongsTo(Objective::class); }
    public function measuredBy(): BelongsTo { return $this->belongsTo(User::class, 'measured_by'); }
}
