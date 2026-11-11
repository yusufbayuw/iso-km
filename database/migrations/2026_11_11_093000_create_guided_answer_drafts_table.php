<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_answer_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('guided_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('respondent_id')->constrained('users')->cascadeOnDelete();
            $table->string('context_key', 120)->default('general');
            $table->json('response')->nullable();
            $table->text('notes')->nullable();
            $table->json('evidence_ids')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['guided_assignment_id', 'respondent_id', 'context_key'], 'guided_draft_owner_context_unique');
            $table->index(['guided_assessment_id', 'respondent_id', 'updated_at'], 'guided_draft_resume_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guided_answer_drafts');
    }
};
