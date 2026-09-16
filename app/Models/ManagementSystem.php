<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManagementSystem extends Model
{
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
