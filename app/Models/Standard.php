<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Standard extends Model
{
    protected $fillable = ['code', 'name', 'edition', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function requirements(): HasMany { return $this->hasMany(Requirement::class); }
    public function controls(): HasMany { return $this->hasMany(Control::class); }
    public function managementSystems(): HasMany { return $this->hasMany(ManagementSystem::class); }
}
