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
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->onDelete('cascade');
            $table->string('part_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->comment('e.g., bearings, belts, sensors, filters');
            $table->string('manufacturer')->nullable();
            $table->string('supplier')->nullable();
            $table->integer('quantity_in_stock')->default(0);
            $table->integer('minimum_stock_level')->default(1);
            $table->integer('reorder_quantity')->default(5);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('location')->nullable()->comment('Warehouse/storage location');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['machine_id', 'category']);
            $table->index('part_number');
            $table->index(['quantity_in_stock', 'minimum_stock_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};
