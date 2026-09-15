<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Evidence extends Model
{
    protected $table = 'evidences';
    protected $fillable = ['management_system_id', 'title', 'type', 'description', 'file_path', 'external_url', 'owner_id', 'captured_at', 'expires_at', 'status'];
    protected function casts(): array { return ['captured_at' => 'date', 'expires_at' => 'date']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class, 'evidence_requirement'); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class, 'control_evidence'); }
    public function documents(): BelongsToMany { return $this->belongsToMany(Document::class, 'document_evidence'); }
}
