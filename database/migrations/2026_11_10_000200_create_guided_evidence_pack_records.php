<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_evidence_pack_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('includes_sensitive')->default(false);
            $table->unsignedInteger('included_count')->default(0);
            $table->unsignedInteger('omitted_count')->default(0);
            $table->string('file_path');
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guided_evidence_pack_records');
    }
};
