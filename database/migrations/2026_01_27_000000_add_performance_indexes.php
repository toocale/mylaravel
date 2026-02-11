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
        Schema::table('production_shifts', function (Blueprint $table) {
            // Index for time-range lookups (e.g. "Get all shifts in the last 30 days")
            $table->index('started_at');
        });

        Schema::table('downtime_events', function (Blueprint $table) {
            // Index for time-range lookups on downtime (e.g. "Global downtime Pareto")
            // Note: machine_id, start_time already exists, but this helps non-machine specific queries
            $table->index('start_time');
            
            // Index to speed up "downtime for these shift IDs" queries
            $table->index('production_shift_id');
        });

        Schema::table('daily_oee_metrics', function (Blueprint $table) {
            // Index for global dashboard trend queries (e.g. "Get avg OEE for last 30 days across all plants")
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_shifts', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
        });

        Schema::table('downtime_events', function (Blueprint $table) {
            $table->dropIndex(['start_time']);
            $table->dropIndex(['production_shift_id']);
        });

        Schema::table('daily_oee_metrics', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });
    }
};
