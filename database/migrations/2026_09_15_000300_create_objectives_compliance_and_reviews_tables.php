<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('metric')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('baseline_value', 14, 4)->nullable();
            $table->decimal('target_value', 14, 4)->nullable();
            $table->string('direction')->default('increase');
            $table->string('frequency')->default('monthly');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('objective_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->decimal('actual_value', 14, 4)->nullable();
            $table->string('status')->default('on_track');
            $table->text('notes')->nullable();
            $table->foreignId('measured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['objective_id', 'period_date']);
        });

        Schema::create('compliance_obligations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('type')->default('regulation');
            $table->string('jurisdiction')->nullable();
            $table->string('reference')->nullable();
            $table->string('source_url')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('applicable');
            $table->date('last_reviewed_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_obligation_management_system', function (Blueprint $table) {
            $table->foreignId('compliance_obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->primary(['compliance_obligation_id', 'management_system_id']);
        });

        Schema::create('management_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_system_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('status')->default('planned');
            $table->foreignId('chairperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('held_at')->nullable();
            $table->text('inputs_summary')->nullable();
            $table->text('outputs_summary')->nullable();
            $table->text('decisions')->nullable();
            $table->timestamps();
        });

        Schema::create('management_review_user', function (Blueprint $table) {
            $table->foreignId('management_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['management_review_id', 'user_id']);
        });

        Schema::create('management_review_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('management_review_id')->constrained()->cascadeOnDelete();
            $table->text('action');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->default('open');
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_review_actions');
        Schema::dropIfExists('management_review_user');
        Schema::dropIfExists('management_reviews');
        Schema::dropIfExists('compliance_obligation_management_system');
        Schema::dropIfExists('compliance_obligations');
        Schema::dropIfExists('objective_measurements');
        Schema::dropIfExists('objectives');
    }
};
