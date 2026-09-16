<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('category')->default('awareness');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_audience')->nullable();
            $table->string('delivery_method')->nullable();
            $table->boolean('mandatory')->default(true);
            $table->unsignedSmallInteger('recurrence_months')->nullable();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->string('status')->default('active');
            $table->date('next_review_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['management_system_id', 'code']);
        });

        Schema::create('requirement_training_program', function (Blueprint $table): void {
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->primary(['requirement_id', 'training_program_id']);
        });

        Schema::create('control_training_program', function (Blueprint $table): void {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'training_program_id']);
        });

        Schema::create('training_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('cycle_key')->default('initial');
            $table->date('assigned_at')->nullable();
            $table->date('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->default('assigned');
            $table->string('result')->default('pending');
            $table->decimal('score', 5, 2)->nullable();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['training_program_id', 'user_id', 'cycle_key'], 'training_assignment_cycle_unique');
        });

        Schema::create('competency_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('competency');
            $table->string('source')->nullable();
            $table->string('proficiency_level')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assessed_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('competency_record_requirement', function (Blueprint $table): void {
            $table->foreignId('competency_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['competency_record_id', 'requirement_id'], 'competency_requirement_pk');
        });

        Schema::create('competency_record_control', function (Blueprint $table): void {
            $table->foreignId('competency_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->primary(['competency_record_id', 'control_id'], 'competency_control_pk');
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('service_description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('criticality')->default('medium');
            $table->string('status')->default('active');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->date('next_review_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('management_system_supplier', function (Blueprint $table): void {
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['management_system_id', 'supplier_id']);
        });

        Schema::create('requirement_supplier', function (Blueprint $table): void {
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['requirement_id', 'supplier_id']);
        });

        Schema::create('control_supplier', function (Blueprint $table): void {
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['control_id', 'supplier_id']);
        });

        Schema::create('evidence_supplier', function (Blueprint $table): void {
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['evidence_id', 'supplier_id']);
        });

        Schema::create('supplier_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assessed_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('risk_rating')->default('medium');
            $table->string('status')->default('planned');
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->text('required_actions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_assessments');
        Schema::dropIfExists('evidence_supplier');
        Schema::dropIfExists('control_supplier');
        Schema::dropIfExists('requirement_supplier');
        Schema::dropIfExists('management_system_supplier');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('competency_record_control');
        Schema::dropIfExists('competency_record_requirement');
        Schema::dropIfExists('competency_records');
        Schema::dropIfExists('training_assignments');
        Schema::dropIfExists('control_training_program');
        Schema::dropIfExists('requirement_training_program');
        Schema::dropIfExists('training_programs');
    }
};
