<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('type')->default('internal');
            $table->string('status')->default('planning');
            $table->foreignId('lead_auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->text('scope')->nullable();
            $table->text('objectives')->nullable();
            $table->text('criteria')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_user', function (Blueprint $table) {
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('auditor');
            $table->primary(['audit_id', 'user_id']);
        });

        Schema::create('audit_requirement', function (Blueprint $table) {
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['audit_id', 'requirement_id']);
        });

        Schema::create('audit_control', function (Blueprint $table) {
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->primary(['audit_id', 'control_id']);
        });

        Schema::create('audit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('control_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->string('result')->default('not_reviewed');
            $table->text('notes')->nullable();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('finding_type')->default('nonconformity');
            $table->string('severity')->nullable();
            $table->string('title');
            $table->text('description');
            $table->foreignId('requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('control_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->default('open');
            $table->text('root_cause')->nullable();
            $table->text('correction')->nullable();
            $table->timestamps();
        });

        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_finding_id')->constrained()->cascadeOnDelete();
            $table->text('action');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->default('open');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('effectiveness_status')->default('pending');
            $table->text('effectiveness_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_checklist_items');
        Schema::dropIfExists('audit_control');
        Schema::dropIfExists('audit_requirement');
        Schema::dropIfExists('audit_user');
        Schema::dropIfExists('audits');
    }
};
