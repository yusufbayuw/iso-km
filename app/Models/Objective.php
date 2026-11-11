<?php

namespace App\Models;

use App\Models\Concerns\EnforcesManagementSystemScope;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    use EnforcesManagementSystemScope;

    use AuditsChanges;

    protected $fillable = ['management_system_id','code','title','description','owner_id','metric','unit','baseline_value','target_value','direction','frequency','status','start_date','end_date'];
    protected function casts(): array { return ['baseline_value' => 'decimal:4','target_value' => 'decimal:4','start_date' => 'date','end_date' => 'date']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function measurements(): HasMany { return $this->hasMany(ObjectiveMeasurement::class)->orderByDesc('period_date'); }
}
