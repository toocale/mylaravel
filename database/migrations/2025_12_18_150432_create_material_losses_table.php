<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained('production_shifts')->onDelete('cascade');
            $table->foreignId('loss_category_id')->constrained('material_loss_categories')->onDelete('restrict');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('machine_id')->nullable()->constrained('machines')->onDelete('set null');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
            
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('cost_estimate', 10, 2)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['shift_id', 'loss_category_id']);
            $table->index(['machine_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_losses');
    }
};
