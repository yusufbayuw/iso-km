<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_export_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_export_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->json('mapping_snapshot');
            $table->string('template_sha256', 64);
            $table->string('output_sha256', 64);
            $table->string('file_path');
            $table->timestamps();
            $table->index(['guided_assessment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guided_export_records');
    }
};
