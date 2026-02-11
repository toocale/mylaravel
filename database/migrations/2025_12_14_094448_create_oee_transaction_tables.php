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
        // 1. Production Logs (Output Tracking)
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            // Start/End of the production run block
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable(); 
            // Counters
            $table->unsignedInteger('good_count')->default(0);
            $table->unsignedInteger('reject_count')->default(0);
            // Operator
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['machine_id', 'start_time']);
        });

        // 2. Downtime Events
        Schema::create('downtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0); // Calculated or entered
            
            $table->text('comment')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['machine_id', 'start_time']);
        });

        // 3. Daily OEE Metrics (Cache/Aggregation)
        Schema::create('daily_oee_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            
            // 0.00 to 100.00
            $table->decimal('availability_score', 5, 2)->default(0);
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('oee_score', 5, 2)->default(0);
            
            // Raw aggregates for easy recalculation
            $table->unsignedInteger('total_good')->default(0);
            $table->unsignedInteger('total_reject')->default(0);
            $table->unsignedInteger('total_planned_production_time')->default(0); // Seconds
            $table->unsignedInteger('total_run_time')->default(0); // Seconds
            $table->unsignedInteger('total_downtime')->default(0); // Seconds

            $table->timestamps();
            
            $table->unique(['machine_id', 'date']);
        });

        // 4. Update Users table to link to Organization
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('daily_oee_metrics');
        Schema::dropIfExists('downtime_events');
        Schema::dropIfExists('production_logs');
    }
};
