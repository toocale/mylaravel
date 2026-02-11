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
        // 1. Organizations (Tenants)
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->nullable();
            $table->timestamps();
        });

        // 2. Plants (Factories)
        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        // 3. Lines (Production Lines)
        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        // 4. Machines (Assets)
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('idle'); // idle, running, down, maintenance
            $table->decimal('default_ideal_cycle_time', 8, 4)->nullable(); // Seconds per unit (backup if not product specific)
            $table->timestamps();
        });

        // 5. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->index();
            $table->timestamps();
        });

        // 6. Machine Product Configurations (Cycle Times)
        Schema::create('machine_product_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('ideal_cycle_time', 8, 4); // Seconds per unit
            $table->timestamps();
            
            $table->unique(['machine_id', 'product_id']);
        });

        // 7. Shifts
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete(); // Shifts are usually plant-specific
            $table->string('name'); // e.g., "Morning A", "Night B"
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
        
        // 8. Reason Codes (for Downtime)
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('description');
            $table->enum('category', ['planned', 'unplanned', 'performance', 'quality']); // Impact type
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reason_codes');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('machine_product_configs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('lines');
        Schema::dropIfExists('plants');
        Schema::dropIfExists('organizations');
    }
};
