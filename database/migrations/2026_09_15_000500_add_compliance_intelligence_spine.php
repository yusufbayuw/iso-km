<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('captured_at');
            $table->string('verification_status')->default('pending')->after('status');
            $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verified_at');
            $table->string('source')->nullable()->after('type');
            $table->string('confidentiality')->default('internal')->after('source');
            $table->index(['management_system_id', 'status', 'verification_status'], 'evidence_assurance_idx');
        });

        Schema::create('evidence_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('evidence_type')->default('record');
            $table->string('frequency')->default('monthly');
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->unsignedSmallInteger('lead_days')->default(7);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->date('next_due_at');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'next_due_at']);
        });

        Schema::create('evidence_schedule_requirement', function (Blueprint $table) {
            $table->foreignId('evidence_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['evidence_schedule_id', 'requirement_id'], 'evidence_schedule_requirement_pk');
        });

        Schema::create('control_evidence_schedule', function (Blueprint $table) {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_schedule_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'evidence_schedule_id'], 'control_evidence_schedule_pk');
        });

        Schema::create('evidence_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('period_label')->nullable();
            $table->string('status')->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->unique(['evidence_schedule_id', 'due_at'], 'evidence_request_schedule_due_unique');
            $table->index(['owner_id', 'status', 'due_at']);
        });

        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('action_key')->unique();
            $table->string('category');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_generated')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'status', 'due_at']);
            $table->index(['management_system_id', 'status', 'priority']);
        });

        Schema::create('assurance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('requirement_readiness', 5, 2)->default(0);
            $table->decimal('control_implementation', 5, 2)->default(0);
            $table->decimal('evidence_coverage', 5, 2)->default(0);
            $table->decimal('evidence_freshness', 5, 2)->default(0);
            $table->decimal('risk_health', 5, 2)->default(100);
            $table->decimal('findings_health', 5, 2)->default(100);
            $table->decimal('action_health', 5, 2)->default(100);
            $table->string('confidence_level')->default('low');
            $table->json('attention_counts')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assurance_snapshots');
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('evidence_requests');
        Schema::dropIfExists('control_evidence_schedule');
        Schema::dropIfExists('evidence_schedule_requirement');
        Schema::dropIfExists('evidence_schedules');

        Schema::table('evidences', function (Blueprint $table) {
            $table->dropIndex('evidence_assurance_idx');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'valid_from', 'verification_status', 'verified_at', 'verification_notes',
                'source', 'confidentiality',
            ]);
        });
    }
};
