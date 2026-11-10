<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standards', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('edition')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code', 'edition']);
        });

        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('requirement');
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['standard_id', 'code']);
            $table->index(['standard_id', 'sort_order']);
        });

        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('controls')->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_group')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['standard_id', 'code']);
            $table->index(['standard_id', 'sort_order']);
        });

        Schema::create('control_requirement', function (Blueprint $table) {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'requirement_id']);
        });

        Schema::create('compliance_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained()->restrictOnDelete();
            $table->foreignId('compliance_scope_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('planning');
            $table->date('target_certification_date')->nullable();
            $table->date('certified_at')->nullable();
            $table->date('certificate_expires_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['standard_id', 'compliance_scope_id']);
        });

        Schema::create('requirement_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('not_assessed');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('last_assessed_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(['management_system_id', 'requirement_id'], 'req_assessment_system_req_unique');
        });

        Schema::create('soa_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->boolean('applicable')->default(true);
            $table->text('justification')->nullable();
            $table->string('implementation_status')->default('not_started');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('implementation_notes')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->timestamps();
            $table->unique(['management_system_id', 'control_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('type')->default('procedure');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confidentiality')->default('internal');
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('review_frequency_months')->default(12);
            $table->date('next_review_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('revision');
            $table->string('file_path')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('effective_at')->nullable();
            $table->date('obsolete_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['document_id', 'revision']);
        });

        Schema::create('document_management_system', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_id', 'management_system_id'], 'document_ms_primary');
        });

        Schema::create('document_requirement', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_id', 'requirement_id']);
        });

        Schema::create('control_document', function (Blueprint $table) {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'document_id']);
        });

        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('record');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('captured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('control_evidence', function (Blueprint $table) {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->primary(['control_id', 'evidence_id']);
        });

        Schema::create('evidence_requirement', function (Blueprint $table) {
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['evidence_id', 'requirement_id']);
        });

        Schema::create('document_evidence', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->primary(['document_id', 'evidence_id']);
        });

        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('likelihood')->default(1);
            $table->unsignedTinyInteger('impact')->default(1);
            $table->unsignedSmallInteger('inherent_score')->default(1);
            $table->string('treatment_strategy')->default('mitigate');
            $table->text('treatment_plan')->nullable();
            $table->unsignedTinyInteger('residual_likelihood')->nullable();
            $table->unsignedTinyInteger('residual_impact')->nullable();
            $table->unsignedSmallInteger('residual_score')->nullable();
            $table->string('status')->default('open');
            $table->date('target_date')->nullable();
            $table->timestamps();
        });

        Schema::create('control_risk', function (Blueprint $table) {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'risk_id']);
        });

        Schema::create('requirement_risk', function (Blueprint $table) {
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->primary(['requirement_id', 'risk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_risk');
        Schema::dropIfExists('control_risk');
        Schema::dropIfExists('risks');
        Schema::dropIfExists('document_evidence');
        Schema::dropIfExists('evidence_requirement');
        Schema::dropIfExists('control_evidence');
        Schema::dropIfExists('evidences');
        Schema::dropIfExists('control_document');
        Schema::dropIfExists('document_requirement');
        Schema::dropIfExists('document_management_system');
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('soa_entries');
        Schema::dropIfExists('requirement_assessments');
        Schema::dropIfExists('management_systems');
        Schema::dropIfExists('compliance_scopes');
        Schema::dropIfExists('control_requirement');
        Schema::dropIfExists('controls');
        Schema::dropIfExists('requirements');
        Schema::dropIfExists('standards');
    }
};
