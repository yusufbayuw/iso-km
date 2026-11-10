<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_scope_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guided_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // asset | activity
            $table->string('category')->nullable();
            $table->string('title');
            $table->boolean('in_scope')->nullable();
            $table->string('classification')->nullable();
            $table->json('related_controls')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['guided_assessment_id', 'kind', 'id'], 'guided_scope_kind_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guided_scope_items');
    }
};
