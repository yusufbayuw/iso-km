<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_assignments', function (Blueprint $table): void {
            $table->dateTime('started_at')->nullable()->after('due_at');
            $table->dateTime('submitted_at')->nullable()->after('started_at');
            $table->foreignId('verified_by')->nullable()->after('evidence_id')->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verified_at');
        });

        Schema::table('supplier_assessments', function (Blueprint $table): void {
            $table->foreignId('requested_by')->nullable()->after('management_system_id')->constrained('users')->nullOnDelete();
            $table->dateTime('requested_at')->nullable()->after('requested_by');
            $table->dateTime('submitted_at')->nullable()->after('assessed_at');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->foreignId('decided_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable()->after('decided_by');
            $table->foreignId('treatment_verified_by')->nullable()->after('required_actions')->constrained('users')->nullOnDelete();
            $table->dateTime('treatment_verified_at')->nullable()->after('treatment_verified_by');
            $table->text('treatment_verification_notes')->nullable()->after('treatment_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropConstrainedForeignId('treatment_verified_by');
            $table->dropColumn(['requested_at', 'submitted_at', 'reviewed_at', 'review_notes', 'decided_at', 'treatment_verified_at', 'treatment_verification_notes']);
        });

        Schema::table('training_assignments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['started_at', 'submitted_at', 'verified_at', 'verification_notes']);
        });
    }
};
