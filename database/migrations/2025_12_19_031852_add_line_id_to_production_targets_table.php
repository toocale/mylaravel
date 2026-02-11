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
        Schema::table('production_targets', function (Blueprint $table) {
            // Add line_id column
            $table->unsignedBigInteger('line_id')->nullable()->after('machine_id');
            
            // Add foreign key
            $table->foreign('line_id')->references('id')->on('lines')->onDelete('cascade');
            
            // Add index for line queries
            $table->index(['line_id', 'effective_from', 'effective_to']);
            
            // Make machine_id nullable since we can now have line-level targets
            $table->unsignedBigInteger('machine_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_targets', function (Blueprint $table) {
            $table->dropForeign(['line_id']);
            $table->dropIndex(['line_id', 'effective_from', 'effective_to']);
            $table->dropColumn('line_id');
            
            // Restore machine_id to not nullable
            $table->unsignedBigInteger('machine_id')->nullable(false)->change();
        });
    }
};
