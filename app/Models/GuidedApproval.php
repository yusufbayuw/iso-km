<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class GuidedApproval extends Model
{
    public $timestamps = true;
    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['approval' => 'Riwayat persetujuan tidak dapat diubah.']));
        static::deleting(fn () => throw ValidationException::withMessages(['approval' => 'Riwayat persetujuan tidak dapat dihapus.']));
    }

    protected $fillable = ['guided_decision_id', 'stage', 'outcome', 'actor_id', 'notes'];
    public function decision(): BelongsTo { return $this->belongsTo(GuidedDecision::class, 'guided_decision_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
