<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_loss_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('kg'); // kg, liters, pieces
            $table->boolean('affects_oee')->default(false);
            $table->boolean('requires_reason')->default(false);
            $table->string('color', 7)->default('#ef4444'); // Hex color for charts
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['active', 'affects_oee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_loss_categories');
    }
};
