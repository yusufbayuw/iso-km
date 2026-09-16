<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    use AuditsChanges;

    protected $table = 'evidences';
    protected $fillable = ['management_system_id', 'title', 'type', 'source', 'confidentiality', 'description', 'file_path', 'external_url', 'owner_id', 'captured_at', 'valid_from', 'expires_at', 'status', 'verification_status', 'verified_by', 'verified_at', 'verification_notes'];
    protected function casts(): array { return ['captured_at' => 'date', 'valid_from' => 'date', 'expires_at' => 'date', 'verified_at' => 'datetime']; }
    public function managementSystem(): BelongsTo { return $this->belongsTo(ManagementSystem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function requirements(): BelongsToMany { return $this->belongsToMany(Requirement::class, 'evidence_requirement'); }
    public function controls(): BelongsToMany { return $this->belongsToMany(Control::class, 'control_evidence'); }
    public function documents(): BelongsToMany { return $this->belongsToMany(Document::class, 'document_evidence'); }
    public function suppliers(): BelongsToMany { return $this->belongsToMany(Supplier::class); }
    public function requests(): HasMany { return $this->hasMany(EvidenceRequest::class); }
}
