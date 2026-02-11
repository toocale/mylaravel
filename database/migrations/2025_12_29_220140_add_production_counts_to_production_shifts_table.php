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
        Schema::table('production_shifts', function (Blueprint $table) {
            $table->integer('good_count')->nullable()->after('status');
            $table->integer('reject_count')->nullable()->after('good_count');
            $table->integer('total_count')->nullable()->after('reject_count');
        });
        
        // Backfill data from metadata
        DB::statement("
            UPDATE production_shifts 
            SET 
                good_count = JSON_EXTRACT(metadata, '$.good_count'),
                reject_count = JSON_EXTRACT(metadata, '$.reject_count')
            WHERE metadata IS NOT NULL
        ");
        
        // Calculate total_count
        DB::statement("
            UPDATE production_shifts 
            SET total_count = COALESCE(good_count, 0) + COALESCE(reject_count, 0)
            WHERE good_count IS NOT NULL OR reject_count IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_shifts', function (Blueprint $table) {
            $table->dropColumn(['good_count', 'reject_count', 'total_count']);
        });
    }
};
