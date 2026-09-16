<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_audit_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('allow_audit_pack')->default(false);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'management_system_id', 'status'], 'external_audit_access_lookup');
        });

        Schema::create('requirement_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requirement_a_id')->constrained('requirements')->cascadeOnDelete();
            $table->foreignId('requirement_b_id')->constrained('requirements')->cascadeOnDelete();
            $table->string('mapping_type')->default('related');
            $table->unsignedTinyInteger('coverage_percent')->default(50);
            $table->text('rationale')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['requirement_a_id', 'requirement_b_id'], 'requirement_mapping_pair_unique');
        });

        Schema::create('control_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('control_a_id')->constrained('controls')->cascadeOnDelete();
            $table->foreignId('control_b_id')->constrained('controls')->cascadeOnDelete();
            $table->string('mapping_type')->default('related');
            $table->unsignedTinyInteger('coverage_percent')->default(50);
            $table->text('rationale')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['control_a_id', 'control_b_id'], 'control_mapping_pair_unique');
        });

        Schema::create('evidence_reuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('target_management_system_id')->constrained('management_systems')->cascadeOnDelete();
            $table->foreignId('target_requirement_id')->nullable()->constrained('requirements')->cascadeOnDelete();
            $table->foreignId('target_control_id')->nullable()->constrained('controls')->cascadeOnDelete();
            $table->foreignId('requirement_mapping_id')->nullable()->constrained('requirement_mappings')->nullOnDelete();
            $table->foreignId('control_mapping_id')->nullable()->constrained('control_mappings')->nullOnDelete();
            $table->string('status')->default('proposed');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['target_management_system_id', 'status'], 'evidence_reuse_target_status');
            $table->index(['evidence_id', 'target_requirement_id'], 'evidence_reuse_requirement_lookup');
            $table->index(['evidence_id', 'target_control_id'], 'evidence_reuse_control_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_reuses');
        Schema::dropIfExists('control_mappings');
        Schema::dropIfExists('requirement_mappings');
        Schema::dropIfExists('external_audit_accesses');
    }
};
