<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineComponent extends Model
{
    protected $fillable = [
        'machine_id',
        'component_name',
        'component_type',
        'manufacturer',
        'model_number',
        'serial_number',
        'installed_at',
        'expected_lifespan_hours',
        'current_runtime_hours',
        'replacement_threshold_hours',
        'status',
        'last_inspected_at',
        'cost',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'last_inspected_at' => 'datetime',
        'cost' => 'decimal:2',
    ];

    // Relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    // Scopes
    public function scopeForMachine($query, $machineId)
    {
        return $query->where('machine_id', $machineId);
    }

    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('status', ['warning', 'critical']);
    }

    // Helper methods
    public function getRemainingLifePercentage()
    {
        if (!$this->expected_lifespan_hours) {
            return null;
        }
        
        $remaining = $this->expected_lifespan_hours - $this->current_runtime_hours;
        return max(0, ($remaining / $this->expected_lifespan_hours) * 100);
    }

    public function updateStatus()
    {
        $percentage = $this->getRemainingLifePercentage();
        
        if ($percentage === null) {
            return;
        }

        if ($percentage <= 0) {
            $this->status = 'critical';
        } elseif ($percentage <= 20) {
            $this->status = 'critical';
        } elseif ($percentage <= 50) {
            $this->status = 'warning';
        } else {
            $this->status = 'good';
        }

        $this->save();
    }
}
