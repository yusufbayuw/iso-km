<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use App\Services\ManagementSystemIntegrityService;
use App\Services\CorrectiveActionWorkflowService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditFinding extends Model
{
    use AuditsChanges;

    protected static function booted(): void
    {
        static::saving(function (self $finding): void {
            $integrity = app(ManagementSystemIntegrityService::class);
            $systemId = $integrity->auditSystemId($finding->audit_id);
            $integrity->assertCatalogItem($systemId, $finding->requirement_id, 'requirement');
            $integrity->assertCatalogItem($systemId, $finding->control_id, 'control');
            if ($finding->exists && $finding->isDirty(['audit_id', 'finding_type'])
                && $finding->correctiveActions()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'finding_type' => 'Jenis dan audit asal finding dengan CAPA tidak dapat diubah.',
                ]);
            }
            if ($finding->status === 'closed' && ($finding->isDirty('status') || ! $finding->exists)) {
                app(CorrectiveActionWorkflowService::class)->assertCanClose($finding);
            }
        });
        static::saved(function (self $finding): void {
            // Reconcile even when a stale Eloquent instance is saved after CAPA
            // transitions; no status update happens when it is already correct.
            app(CorrectiveActionWorkflowService::class)->syncFinding($finding);
        });
    }

    protected $fillable = ['audit_checklist_item_id','audit_id','code','finding_type','severity','title','description','requirement_id','control_id','owner_id','due_date','status','root_cause','correction'];
    protected function casts(): array { return ['due_date' => 'date']; }
    public function checklistItem(): BelongsTo { return $this->belongsTo(AuditChecklistItem::class, 'audit_checklist_item_id'); }
    public function audit(): BelongsTo { return $this->belongsTo(Audit::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function correctiveActions(): HasMany { return $this->hasMany(CorrectiveAction::class); }
}
