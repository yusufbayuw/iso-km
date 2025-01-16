<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class M002Control extends Model
{
    public function iso(): BelongsTo
    {
        return $this->belongsTo(M001Iso::class, 'iso_id');
    }

    public function document(): HasMany
    {
        return $this->hasMany(M003Document::class, 'control_id');
    }
}
