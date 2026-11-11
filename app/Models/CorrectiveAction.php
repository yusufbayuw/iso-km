<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use App\Services\CorrectiveActionWorkflowService;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveAction extends Model
{
    use AuditsChanges;

    protected static function booted(): void
    {
        static::saving(function (self $action): void {
            if ($action->status === 'verification' && $action->completed_at === null) {
                $action->completed_at = now();
            }
            if ($action->status === 'completed' && $action->isDirty('status')) {
                if ($action->effectiveness_status !== 'effective' || ! $action->verified_by || ! $action->verified_at) {
                    throw ValidationException::withMessages(['status' => 'CAPA harus diverifikasi efektif sebelum selesai.']);
                }
                if ($action->owner_id !== null && (int) $action->owner_id === (int) $action->verified_by) {
                    throw ValidationException::withMessages(['verified_by' => 'Pelaksana tidak boleh memverifikasi pekerjaannya sendiri.']);
                }
            }
        });
        static::saving(function (self $action): void {
            if (! $action->exists) {
                return;
            }
            if ($action->getRawOriginal('status') === 'completed' && $action->isDirty()) {
                throw ValidationException::withMessages([
                    'status' => 'CAPA yang telah diverifikasi bersifat final. Buat tindakan baru untuk revisi.',
                ]);
            }
            if ($action->getRawOriginal('status') === 'verification'
                && $action->isDirty(['owner_id', 'audit_finding_id', 'action'])) {
                throw ValidationException::withMessages([
                    'owner_id' => 'PIC dan isi pekerjaan yang sedang diverifikasi tidak boleh diubah.',
                ]);
            }
        });
        static::deleting(fn (self $action) => throw ValidationException::withMessages([
            'status' => 'Histori CAPA tidak boleh dihapus. Gunakan tindakan lanjutan untuk koreksi.',
        ]));
        static::saved(function (self $action): void {
            if ($action->audit_finding_id) {
                app(CorrectiveActionWorkflowService::class)->syncFinding($action->finding);
            }
        });
    }

    protected $fillable = ['audit_finding_id','action','owner_id','due_date','status','completed_at','verified_by','verified_at','verification_notes','effectiveness_status','effectiveness_notes'];
    protected function casts(): array { return ['due_date' => 'date','completed_at' => 'datetime','verified_at' => 'datetime']; }
    public function finding(): BelongsTo { return $this->belongsTo(AuditFinding::class, 'audit_finding_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
