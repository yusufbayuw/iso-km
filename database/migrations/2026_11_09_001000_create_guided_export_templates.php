<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_export_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('management_system_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->json('mappings');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['management_system_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guided_export_templates');
    }
};
