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
        Schema::table('material_loss_categories', function (Blueprint $table) {
            // Add loss type to differentiate raw material from packaging
            $table->enum('loss_type', ['raw_material', 'packaging', 'other'])
                ->default('raw_material')
                ->after('code');
            // raw_material = needs conversion (milk, powder, juice)
            // packaging = already in finished units (bottles, caps, labels)
            // other = miscellaneous
            
            // Remove the static unit field (no longer needed)
            $table->dropColumn('unit');
            
            $table->index('loss_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_loss_categories', function (Blueprint $table) {
            $table->dropIndex(['loss_type']);
            $table->string('unit')->default('units')->after('description');
            $table->dropColumn('loss_type');
        });
    }
};
