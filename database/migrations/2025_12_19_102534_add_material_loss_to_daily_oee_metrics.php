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
        Schema::table('daily_oee_metrics', function (Blueprint $table) {
            $table->decimal('total_material_loss', 10, 2)->default(0)->after('total_reject');
            $table->decimal('material_loss_cost', 10, 2)->nullable()->after('total_material_loss');
        });
        
        // Add index to material_losses for better performance
        Schema::table('material_losses', function (Blueprint $table) {
            $table->index(['shift_id', 'loss_category_id'], 'idx_material_losses_shift_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_oee_metrics', function (Blueprint $table) {
            $table->dropColumn(['total_material_loss', 'material_loss_cost']);
        });
        
        Schema::table('material_losses', function (Blueprint $table) {
            $table->dropIndex('idx_material_losses_shift_category');
        });
    }
};
