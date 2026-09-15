<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_revisions', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('revision');
            $table->foreignId('reviewer_id')->nullable()->after('prepared_by')->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->after('reviewer_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_review_at')->nullable()->after('approver_id');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->timestamp('rejected_at')->nullable()->after('reviewed_at');
            $table->text('review_notes')->nullable()->after('rejected_at');
            $table->index(['document_id', 'status']);
        });

        Schema::create('document_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('distributed_at')->nullable();
            $table->date('acknowledgement_due_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['document_id', 'user_id', 'document_revision_id'], 'document_distribution_unique');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('category');
            $table->string('reminder_key');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['remindable_type', 'remindable_id', 'recipient_id', 'category', 'reminder_key'], 'reminder_dispatch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('document_distributions');

        Schema::table('document_revisions', function (Blueprint $table) {
            $table->dropIndex(['document_id', 'status']);
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn([
                'status', 'submitted_for_review_at', 'reviewed_at',
                'rejected_at', 'review_notes',
            ]);
        });
    }
};
