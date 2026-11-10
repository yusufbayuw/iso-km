<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guided_export_templates', function (Blueprint $table): void {
            $table->string('report_type')->default('custom')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('guided_export_templates', function (Blueprint $table): void {
            $table->dropColumn('report_type');
        });
    }
};
