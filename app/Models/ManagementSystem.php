<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManagementSystem extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $system): void {
            if (! $system->isDirty(['standard_id', 'compliance_scope_id'])) {
                return;
            }

            $hasOperationalData = $system->requirementAssessments()->exists()
                || $system->soaEntries()->exists()
                || $system->documents()->exists()
                || $system->evidences()->exists()
                || $system->evidenceSchedules()->exists()
                || $system->evidenceRequests()->exists()
                || $system->evidenceReuses()->exists()
                || $system->risks()->exists()
                || $system->audits()->exists()
                || $system->objectives()->exists()
                || $system->managementReviews()->exists()
                || $system->trainingPrograms()->exists()
                || $system->supplierAssessments()->exists();

            if ($hasOperationalData) {
                throw ValidationException::withMessages([
                    $system->isDirty('standard_id') ? 'standard_id' : 'compliance_scope_id' =>
                        'Standar atau lingkup sistem yang sudah memiliki data operasional tidak boleh dipindah tanpa migrasi terkontrol.',
                ]);
            }
        });
    }

    protected $fillable = ['standard_id', 'compliance_scope_id', 'code', 'name', 'status', 'target_certification_date', 'certified_at', 'certificate_expires_at', 'description'];
    protected function casts(): array { return ['target_certification_date' => 'date', 'certified_at' => 'date', 'certificate_expires_at' => 'date']; }
    public function standard(): BelongsTo { return $this->belongsTo(Standard::class); }
    public function scope(): BelongsTo { return $this->belongsTo(ComplianceScope::class, 'compliance_scope_id'); }
    public function requirementAssessments(): HasMany { return $this->hasMany(RequirementAssessment::class); }
    public function soaEntries(): HasMany { return $this->hasMany(StatementOfApplicability::class); }
    public function documents(): BelongsToMany { return $this->belongsToMany(Document::class); }
    public function evidences(): HasMany { return $this->hasMany(Evidence::class); }
    public function evidenceSchedules(): HasMany { return $this->hasMany(EvidenceSchedule::class); }
    public function evidenceRequests(): HasMany { return $this->hasMany(EvidenceRequest::class); }
    public function evidenceReuses(): HasMany { return $this->hasMany(EvidenceReuse::class, 'target_management_system_id'); }
    public function externalAuditAccesses(): HasMany { return $this->hasMany(ExternalAuditAccess::class); }
    public function actionItems(): HasMany { return $this->hasMany(ActionItem::class); }
    public function assuranceSnapshot(): HasOne { return $this->hasOne(AssuranceSnapshot::class); }
    public function risks(): HasMany { return $this->hasMany(Risk::class); }
    public function audits(): HasMany { return $this->hasMany(Audit::class); }
    public function objectives(): HasMany { return $this->hasMany(Objective::class); }
    public function complianceObligations(): BelongsToMany { return $this->belongsToMany(ComplianceObligation::class); }
    public function managementReviews(): HasMany { return $this->hasMany(ManagementReview::class); }
    public function trainingPrograms(): HasMany { return $this->hasMany(TrainingProgram::class); }
    public function competencyRecords(): HasMany { return $this->hasMany(CompetencyRecord::class); }
    public function suppliers(): BelongsToMany { return $this->belongsToMany(Supplier::class); }
    public function supplierAssessments(): HasMany { return $this->hasMany(SupplierAssessment::class); }
}
