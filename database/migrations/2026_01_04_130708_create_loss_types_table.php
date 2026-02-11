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
        Schema::create('loss_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');           // e.g., "Raw Material", "Packaging"
            $table->string('code', 50)->unique();  // e.g., "raw_material", "packaging"
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#ef4444'); // Hex color for UI
            $table->boolean('affects_oee')->default(false); // Does this loss affect OEE?
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['organization_id', 'active']);
        });
        
        // Add loss_type_id column to material_loss_categories table
        Schema::table('material_loss_categories', function (Blueprint $table) {
            $table->foreignId('loss_type_id')->nullable()->after('loss_type')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_loss_categories', function (Blueprint $table) {
            $table->dropForeign(['loss_type_id']);
            $table->dropColumn('loss_type_id');
        });
        
        Schema::dropIfExists('loss_types');
    }
};
