<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guided_assignments', function (Blueprint $table): void {
            $table->boolean('is_auto_assigned')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('guided_assignments', function (Blueprint $table): void {
            $table->dropColumn('is_auto_assigned');
        });
    }
};
