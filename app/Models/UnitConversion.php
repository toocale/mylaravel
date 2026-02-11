<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UnitConversion extends Model
{
    protected $fillable = [
        'name',
        'code',
        'alias',
        'category',
        'to_base_factor',
        'base_unit_code',
        'is_base',
        'active',
    ];

    protected $casts = [
        'to_base_factor' => 'decimal:6',
        'is_base' => 'boolean',
        'active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get a map of unit codes to their conversion factors.
     * This replaces the hardcoded conversions array.
     * 
     * @return array<string, float>
     */
    public static function getConversionMap(): array
    {
        return Cache::remember('unit_conversions_map', 3600, function () {
            $map = [];
            $units = self::active()->get();
            
            foreach ($units as $unit) {
                $map[strtolower($unit->code)] = (float) $unit->to_base_factor;
                
                // Also add alias if exists
                if ($unit->alias) {
                    $map[strtolower($unit->alias)] = (float) $unit->to_base_factor;
                }
            }
            
            return $map;
        });
    }

    /**
     * Clear the cached conversion map (call after updates).
     */
    public static function clearCache(): void
    {
        Cache::forget('unit_conversions_map');
    }

    /**
     * Get conversion factor for a given unit code.
     * Returns 1 if unit not found.
     */
    public static function getConversionFactor(string $unitCode): float
    {
        $map = self::getConversionMap();
        return $map[strtolower($unitCode)] ?? 1;
    }

    /**
     * Convert a quantity from one unit to base unit.
     */
    public static function toBaseUnit(float $quantity, string $unitCode): float
    {
        return $quantity * self::getConversionFactor($unitCode);
    }

    /**
     * Get all units formatted for select dropdowns.
     */
    public static function getForDropdown(?string $category = null): array
    {
        $query = self::active()->orderBy('category')->orderBy('name');
        
        if ($category) {
            $query->byCategory($category);
        }
        
        return $query->get()->map(fn($u) => [
            'value' => $u->code,
            'label' => $u->name,
            'category' => $u->category,
        ])->toArray();
    }
}
