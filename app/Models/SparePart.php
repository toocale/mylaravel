<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePart extends Model
{
    protected $fillable = [
        'machine_id',
        'part_number',
        'name',
        'description',
        'category',
        'manufacturer',
        'supplier',
        'quantity_in_stock',
        'minimum_stock_level',
        'reorder_quantity',
        'unit_cost',
        'location',
        'is_active',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function usages()
    {
        return $this->hasMany(SparePartUsage::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForMachine($query, $machineId)
    {
        return $query->where(function($q) use ($machineId) {
            $q->where('machine_id', $machineId)
              ->orWhereNull('machine_id'); // Include global parts
        });
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity_in_stock <= minimum_stock_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_in_stock', 0);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Helper methods
    public function isLowStock(): bool
    {
        return $this->quantity_in_stock <= $this->minimum_stock_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity_in_stock <= 0;
    }

    public function decrementStock(int $quantity): bool
    {
        if ($this->quantity_in_stock < $quantity) {
            return false;
        }
        
        $this->quantity_in_stock -= $quantity;
        return $this->save();
    }

    public function incrementStock(int $quantity): bool
    {
        $this->quantity_in_stock += $quantity;
        return $this->save();
    }

    public function getTotalUsageCount(): int
    {
        return $this->usages()->sum('quantity_used');
    }

    public function getTotalUsageCost(): float
    {
        return (float) $this->usages()
            ->selectRaw('SUM(quantity_used * COALESCE(cost_at_use, 0)) as total')
            ->value('total') ?? 0;
    }

    // Get stock status for display
    public function getStockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }
        if ($this->isLowStock()) {
            return 'low_stock';
        }
        return 'in_stock';
    }
}
