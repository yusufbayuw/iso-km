<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CompetencyRecord extends Model
{
    use AuditsChanges;

    protected $fillable = ['management_system_id','user_id','competency','source','proficiency_level','assessed_by','assessed_at','valid_until','status','evidence_id','notes'];
    protected function casts(): array { return ['assessed_at' => 'date','valid_until' => 'date']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessed_by'); }
    public function evidence(): BelongsTo { return $this->belongsTo(Evidence::class); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class, 'competency_record_requirement'); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class, 'competency_record_control'); }
}
