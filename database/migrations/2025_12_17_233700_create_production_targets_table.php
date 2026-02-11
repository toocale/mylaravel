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
        Schema::create('production_targets', function (Blueprint $table) {
            $table->id();
            
            // Machine and shift
            $table->unsignedBigInteger('machine_id');
            $table->unsignedBigInteger('shift_id')->nullable(); // NULL means applies to all shifts
            
            // Effective date range
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // NULL means indefinite
            
            // Target metrics (percentages 0-100)
            $table->decimal('target_oee', 5, 2)->nullable();
            $table->decimal('target_availability', 5, 2)->nullable();
            $table->decimal('target_performance', 5, 2)->nullable();
            $table->decimal('target_quality', 5, 2)->nullable();
            
            // Production volume targets
            $table->integer('target_units')->nullable(); // Expected units per shift
            $table->integer('target_good_units')->nullable(); // Expected good units per shift
            
            // Metadata
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('machine_id')->references('id')->on('machines')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['machine_id', 'effective_from', 'effective_to']);
            $table->index(['machine_id', 'shift_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_targets');
    }
};
