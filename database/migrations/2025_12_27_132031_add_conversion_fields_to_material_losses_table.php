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
        Schema::table('material_losses', function (Blueprint $table) {
            // Loss type (from category, denormalized for performance)
            $table->enum('loss_type', ['raw_material', 'packaging', 'other'])
                ->default('raw_material')
                ->after('loss_category_id');
            
            // Calculated field: quantity converted to finished units
            $table->decimal('finished_units_lost', 10, 2)->nullable()->after('quantity');
            // This will be auto-calculated based on product conversion factors
            
            $table->index('loss_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_losses', function (Blueprint $table) {
            $table->dropIndex(['loss_type']);
            $table->dropColumn(['loss_type', 'finished_units_lost']);
        });
    }
};
