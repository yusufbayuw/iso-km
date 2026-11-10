<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_question_banks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standard_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['standard_id', 'code', 'version'], 'guided_bank_version_unique');
        });

        Schema::create('guided_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->string('answer_type')->default('single_choice');
            $table->json('answer_options')->nullable();
            $table->json('branch_rules')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['guided_question_bank_id', 'code'], 'guided_question_bank_code_unique');
        });

        Schema::create('guided_question_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('control_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('criterion');
            $table->string('evidence_profile')->default('standard');
            $table->json('evidence_hints')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->index(['guided_question_id', 'requirement_id'], 'guided_target_requirement_idx');
            $table->index(['guided_question_id', 'control_id'], 'guided_target_control_idx');
        });

        Schema::create('guided_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_question_bank_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('high_risk_threshold')->default(12);
            $table->unsignedTinyInteger('critical_risk_threshold')->default(20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index(['management_system_id', 'status']);
        });

        Schema::create('guided_assessment_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['guided_assessment_id', 'user_id', 'role'], 'guided_member_role_unique');
        });

        Schema::create('guided_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('assigned');
            $table->date('due_at')->nullable();
            $table->timestamps();
            $table->unique(['guided_assessment_id', 'guided_question_id', 'user_id'], 'guided_assignment_unique');
        });

        Schema::create('guided_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('guided_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('respondent_id')->constrained('users')->restrictOnDelete();
            $table->string('context_key')->default('general');
            $table->json('response');
            $table->text('notes')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('guided_answers')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['guided_assessment_id', 'guided_question_id', 'context_key'], 'guided_answer_lookup');
        });

        Schema::create('guided_answer_evidence', function (Blueprint $table): void {
            $table->foreignId('guided_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->restrictOnDelete();
            $table->primary(['guided_answer_id', 'evidence_id']);
        });

        Schema::create('guided_consolidations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_question_id')->constrained()->restrictOnDelete();
            $table->string('context_key')->default('general');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('method')->default('summary');
            $table->text('summary');
            $table->text('reason')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['guided_assessment_id', 'guided_question_id', 'context_key', 'version'], 'guided_consolidation_version_unique');
        });

        Schema::create('guided_consolidation_sources', function (Blueprint $table): void {
            $table->foreignId('guided_consolidation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_answer_id')->constrained()->restrictOnDelete();
            $table->string('usage')->default('used');
            $table->text('reason')->nullable();
            $table->primary(['guided_consolidation_id', 'guided_answer_id'], 'guided_consolidation_source_pk');
        });

        Schema::create('guided_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_consolidation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_question_target_id')->constrained()->restrictOnDelete();
            $table->string('proposed_status');
            $table->boolean('applicable')->nullable();
            $table->text('rationale');
            $table->unsignedTinyInteger('risk_score')->default(1);
            $table->foreignId('risk_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('submitted');
            $table->string('baseline_status')->nullable();
            $table->timestamp('baseline_updated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['guided_consolidation_id', 'guided_question_target_id'], 'guided_decision_target_unique');
        });

        Schema::create('guided_evidence_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_decision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->text('rationale')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['guided_decision_id', 'evidence_id'], 'guided_evidence_decision_unique');
        });

        Schema::create('guided_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_decision_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('outcome');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['guided_decision_id', 'stage', 'created_at'], 'guided_approval_stage_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'guided_approvals', 'guided_evidence_acceptances', 'guided_decisions',
            'guided_consolidation_sources', 'guided_consolidations', 'guided_answer_evidence',
            'guided_answers', 'guided_assignments', 'guided_assessment_memberships',
            'guided_assessments', 'guided_question_targets', 'guided_questions', 'guided_question_banks',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
