<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasProperCase;

class Product extends Model
{
    use HasProperCase;
    
    protected $guarded = [];
    
    protected $properCaseAttributes = ['name', 'description'];
    
    protected $fillable = [
        'name',
        'description',
        'sku',
        'unit_of_measure',
        'organization_id',       // Required for multi-tenancy
        'finished_unit',         // NEW: Finished product unit (bottles, boxes, pieces)
        'fill_volume',           // NEW: How much raw material per unit
        'fill_volume_unit',      // NEW: Unit of fill_volume
        'reference_weight',
    ];

    protected $casts = [
        'reference_weight' => 'decimal:4',
        'fill_volume' => 'decimal:4',
    ];
    
    /**
     * Convert raw material quantity to finished units
     * 
     * Example: 100 liters → bottles
     * If fill_volume = 500ml (0.5 liters)
     * Result: 100 ÷ 0.5 = 200 bottles
     */
    public function convertToFinishedUnits(float $rawQuantity, string $rawUnit): float
    {
        if (!$this->fill_volume || !$this->fill_volume_unit) {
            return $rawQuantity; // No conversion available
        }
        
        // Normalize units to same scale
        $rawQuantityNormalized = $this->normalizeToBaseUnit($rawQuantity, $rawUnit);
        $fillVolumeNormalized = $this->normalizeToBaseUnit($this->fill_volume, $this->fill_volume_unit);
        
        // Calculate finished units
        return $rawQuantityNormalized / $fillVolumeNormalized;
    }
    
    /**
     * Normalize different units to base unit using database configuration.
     * Falls back to hardcoded values if database table doesn't exist.
     */
    private function normalizeToBaseUnit(float $quantity, string $unit): float
    {
        // Try to use database-backed conversions
        try {
            if (class_exists(UnitConversion::class) && \Schema::hasTable('unit_conversions')) {
                $multiplier = UnitConversion::getConversionFactor($unit);
                return $quantity * $multiplier;
            }
        } catch (\Exception $e) {
            // Fall back to hardcoded values
        }
        
        // Fallback hardcoded conversions (for backwards compatibility)
        $conversions = [
            // Volume
            'liters' => 1000,    // 1 liter = 1000 ml
            'l' => 1000,
            'ml' => 1,
            'milliliters' => 1,
            
            // Weight
            'kg' => 1000,        // 1 kg = 1000 grams
            'kilograms' => 1000,
            'grams' => 1,
            'g' => 1,
            
            // Count (no conversion)
            'pieces' => 1,
            'units' => 1,
            'bottles' => 1,
            'boxes' => 1,
            'cartons' => 1,
            'sachets' => 1,
        ];
        
        $multiplier = $conversions[strtolower($unit)] ?? 1;
        return $quantity * $multiplier;
    }
}
