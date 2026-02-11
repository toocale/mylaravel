<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('downtime_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');           // e.g., "Planned", "Unplanned"
            $table->string('code', 50)->unique();  // e.g., "planned", "unplanned"
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6b7280'); // Hex color for UI
            $table->boolean('affects_availability')->default(true); // Does this downtime affect availability?
            $table->boolean('is_default')->default(false); // System default types
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['organization_id', 'active']);
        });
        
        // Add downtime_type_id column to reason_codes table
        Schema::table('reason_codes', function (Blueprint $table) {
            $table->foreignId('downtime_type_id')->nullable()->after('category')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reason_codes', function (Blueprint $table) {
            $table->dropForeign(['downtime_type_id']);
            $table->dropColumn('downtime_type_id');
        });
        
        Schema::dropIfExists('downtime_types');
    }
};
