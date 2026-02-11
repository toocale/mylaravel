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
        Schema::create('machine_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->string('component_name');
            $table->string('component_type')->nullable()->comment('e.g., bearing, belt, motor, sensor');
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->integer('expected_lifespan_hours')->nullable();
            $table->integer('current_runtime_hours')->default(0);
            $table->integer('replacement_threshold_hours')->nullable();
            $table->enum('status', ['good', 'warning', 'critical', 'replaced'])->default('good');
            $table->timestamp('last_inspected_at')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamps();
            
            $table->index(['machine_id', 'status']);
            $table->index('component_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_components');
    }
};
