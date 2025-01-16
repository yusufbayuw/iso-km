<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('m003_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->nullable()->constrained('m002_controls')->cascadeOnDelete();
            $table->string('nama')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('versi')->nullable();
            $table->string('file')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m003_documents');
    }
};
