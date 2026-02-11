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
        Schema::table('products', function (Blueprint $table) {
            // Finished unit (what we count in production)
            $table->string('finished_unit')->default('units')->after('unit_of_measure');
            // e.g., "bottles", "boxes", "pieces", "cartons"
            
            // Conversion factor: how much raw material per finished unit
            $table->decimal('fill_volume', 10, 4)->nullable()->after('finished_unit');
            // e.g., 500 (ml per bottle), 1000 (grams per box)
            
            $table->string('fill_volume_unit')->nullable()->after('fill_volume');
            // e.g., "ml", "grams", "liters"
            
            // Add index for performance
            $table->index('finished_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['finished_unit']);
            $table->dropColumn(['finished_unit', 'fill_volume', 'fill_volume_unit']);
        });
    }
};
