<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_findings', function (Blueprint $table): void {
            // Nullable for all existing findings; one generated finding per checklist item.
            $table->foreignId('audit_checklist_item_id')->nullable()
                ->constrained('audit_checklist_items')->nullOnDelete()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('audit_checklist_item_id');
        });
    }
};
