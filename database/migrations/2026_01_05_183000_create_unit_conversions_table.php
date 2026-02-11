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
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // Display name (e.g., "Liters", "Kilograms")
            $table->string('code')->unique();           // Code for matching (e.g., "liters", "kg")
            $table->string('alias')->nullable();        // Alternative code (e.g., "l" for liters)
            $table->enum('category', ['volume', 'weight', 'count'])->default('count');
            $table->decimal('to_base_factor', 12, 6);   // Factor to convert to base unit (e.g., 1000 for liters → ml)
            $table->string('base_unit_code');           // The base unit for this category (e.g., "ml", "g", "units")
            $table->boolean('is_base')->default(false); // True if this IS the base unit
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed default units
        $this->seedDefaultUnits();
    }

    /**
     * Seed the default unit conversions (replaces hardcoded values).
     */
    private function seedDefaultUnits(): void
    {
        $units = [
            // Volume (base: ml)
            ['name' => 'Milliliters', 'code' => 'ml', 'alias' => 'milliliters', 'category' => 'volume', 'to_base_factor' => 1, 'base_unit_code' => 'ml', 'is_base' => true],
            ['name' => 'Liters', 'code' => 'liters', 'alias' => 'l', 'category' => 'volume', 'to_base_factor' => 1000, 'base_unit_code' => 'ml', 'is_base' => false],
            
            // Weight (base: g)
            ['name' => 'Grams', 'code' => 'grams', 'alias' => 'g', 'category' => 'weight', 'to_base_factor' => 1, 'base_unit_code' => 'g', 'is_base' => true],
            ['name' => 'Kilograms', 'code' => 'kg', 'alias' => 'kilograms', 'category' => 'weight', 'to_base_factor' => 1000, 'base_unit_code' => 'g', 'is_base' => false],
            
            // Count (base: units)
            ['name' => 'Units', 'code' => 'units', 'alias' => null, 'category' => 'count', 'to_base_factor' => 1, 'base_unit_code' => 'units', 'is_base' => true],
            ['name' => 'Pieces', 'code' => 'pieces', 'alias' => 'pcs', 'category' => 'count', 'to_base_factor' => 1, 'base_unit_code' => 'units', 'is_base' => false],
            ['name' => 'Bottles', 'code' => 'bottles', 'alias' => null, 'category' => 'count', 'to_base_factor' => 1, 'base_unit_code' => 'units', 'is_base' => false],
            ['name' => 'Boxes', 'code' => 'boxes', 'alias' => null, 'category' => 'count', 'to_base_factor' => 1, 'base_unit_code' => 'units', 'is_base' => false],
            ['name' => 'Cartons', 'code' => 'cartons', 'alias' => null, 'category' => 'count', 'to_base_factor' => 1, 'base_unit_code' => 'units', 'is_base' => false],
            ['name' => 'Sachets', 'code' => 'sachets', 'alias' => null, 'category' => 'count', 'to_base_factor' => 1, 'base_unit_code' => 'units', 'is_base' => false],
        ];

        foreach ($units as $unit) {
            \DB::table('unit_conversions')->insert(array_merge($unit, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
    }
};
