<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use App\Services\ManagementSystemIntegrityService;
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
        });
    }

    protected $fillable = ['audit_id','code','finding_type','severity','title','description','requirement_id','control_id','owner_id','due_date','status','root_cause','correction'];
    protected function casts(): array { return ['due_date' => 'date']; }
    public function audit(): BelongsTo { return $this->belongsTo(Audit::class); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function control(): BelongsTo { return $this->belongsTo(Control::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function correctiveActions(): HasMany { return $this->hasMany(CorrectiveAction::class); }
}
