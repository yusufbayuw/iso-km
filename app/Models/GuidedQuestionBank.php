<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class GuidedQuestionBank extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $bank): void {
            if ($bank->getOriginal('status') === 'published') {
                $changes = array_keys($bank->getDirty());
                if ($changes !== ['status'] || $bank->status !== 'retired') {
                    throw ValidationException::withMessages(['bank' => 'Bank terbit tidak bisa diubah. Buat versi baru.']);
                }
            }
        });
        static::deleting(function (self $bank): void {
            if ($bank->status !== 'draft') {
                throw ValidationException::withMessages(['bank' => 'Bank yang diterbitkan tidak dapat dihapus.']);
            }
        });
    }

    protected $attributes = ['status' => 'draft', 'version' => 1];
    protected $fillable = ['standard_id', 'code', 'name', 'version', 'status', 'created_by'];
    public function standard(): BelongsTo { return $this->belongsTo(Standard::class); }
    public function questions(): HasMany { return $this->hasMany(GuidedQuestion::class)->orderBy('sort_order'); }
}
