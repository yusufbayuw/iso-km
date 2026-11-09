<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidedQuestionBank extends Model
{
    protected $fillable = ['standard_id', 'code', 'name', 'version', 'status', 'created_by'];
    public function standard(): BelongsTo { return $this->belongsTo(Standard::class); }
    public function questions(): HasMany { return $this->hasMany(GuidedQuestion::class)->orderBy('sort_order'); }
}
