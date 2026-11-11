<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    use AuditsChanges;

    protected $table = 'evidences';
    protected static function booted(): void
    {
        static::deleting(function (self $evidence): void {
            if ($evidence->verification_status === 'verified'
                || $evidence->requests()->exists()
                || $evidence->reuses()->exists()
                || DB::table('audit_checklist_items')->where('evidence_id', $evidence->id)->exists()
                || DB::table('guided_answer_evidence')->where('evidence_id', $evidence->id)->exists()
                || DB::table('guided_evidence_acceptances')->where('evidence_id', $evidence->id)->exists()) {
                throw ValidationException::withMessages([
                    'evidence' => 'Evidence yang sudah diverifikasi atau digunakan dalam proses tidak dapat dihapus. Gunakan arsip.',
                ]);
            }
        });
    }

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
    public function reuses(): HasMany { return $this->hasMany(EvidenceReuse::class); }
}
