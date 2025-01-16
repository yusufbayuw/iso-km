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
        Schema::create('m002_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iso_id')->nullable()->constrained('m001_isos')->cascadeOnDelete();
            $table->string('nama')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m002_controls');
    }
};
