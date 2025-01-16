<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class M001Iso extends Model
{
    public function control(): HasMany
    {
        return $this->hasMany(M002Control::class, 'iso_id');
    }
}
