<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidedApproval extends Model
{
    public $timestamps = true;
    protected $fillable = ['guided_decision_id', 'stage', 'outcome', 'actor_id', 'notes'];
    public function decision(): BelongsTo { return $this->belongsTo(GuidedDecision::class, 'guided_decision_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
