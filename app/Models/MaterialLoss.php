<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialLoss extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'loss_category_id',
        'product_id',
        'machine_id',
        'recorded_by',
        'loss_type',              // NEW
        'quantity',
        'unit',
        'finished_units_lost',    // NEW: Auto-calculated
        'reason',
        'notes',
        'cost_estimate',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'finished_units_lost' => 'decimal:2',
        'cost_estimate' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];
    
    /**
     * Boot method - auto-calculate finished_units_lost when saving
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($materialLoss) {
            if ($materialLoss->product_id && $materialLoss->quantity) {
                $materialLoss->calculateFinishedUnitsLost();
            }
        });
    }

    protected $appends = [
        'estimated_cost',
        'equivalent_units',
    ];

    // Relationships
    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function category()
    {
        return $this->belongsTo(MaterialLossCategory::class, 'loss_category_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Scopes
    public function scopeForShift($query, $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeForMachine($query, $machineId)
    {
        return $query->where('machine_id', $machineId);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('loss_category_id', $categoryId);
    }

    // Accessors
    public function getEstimatedCostAttribute()
    {
        if ($this->cost_estimate) {
            return $this->cost_estimate;
        }
        
        // Calculate from product cost if available
        if ($this->product && $this->product->unit_cost) {
            return $this->quantity * $this->product->unit_cost;
        }
        
        return null;
    }

    public function getEquivalentUnitsAttribute()
    {
        // Calculate based on product reference weight if available
        if ($this->product && $this->product->reference_weight && $this->product->reference_weight > 0) {
            return round($this->quantity / $this->product->reference_weight, 2);
        }
        
        return null;
    }
    
    /**
     * Calculate finished units lost based on loss type
     * Called automatically on save
     */
    public function calculateFinishedUnitsLost(): void
    {
        if (!$this->product) {
            // Load product if not already loaded
            $this->load('product');
        }
        
        if (!$this->product) {
            $this->finished_units_lost = $this->quantity;
            return;
        }
        
        if ($this->loss_type === 'raw_material') {
            // Convert raw material to finished units using product conversion
            $this->finished_units_lost = $this->product->convertToFinishedUnits(
                $this->quantity,
                $this->unit ?? $this->product->unit_of_measure
            );
        } elseif ($this->loss_type === 'packaging') {
            // Packaging is already in finished units (1 bottle wasted = 1 bottle)
            $this->finished_units_lost = $this->quantity;
        } else {
            // Other types: default to quantity
            $this->finished_units_lost = $this->quantity;
        }
    }
}
