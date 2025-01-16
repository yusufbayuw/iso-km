<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class M003Document extends Model
{
    public function control(): BelongsTo
    {
        return $this->belongsTo(M002Control::class, 'control_id');
    }
}
