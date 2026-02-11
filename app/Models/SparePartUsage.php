<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePartUsage extends Model
{
    protected $fillable = [
        'spare_part_id',
        'maintenance_log_id',
        'machine_id',
        'used_by_user_id',
        'quantity_used',
        'cost_at_use',
        'used_at',
        'notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'cost_at_use' => 'decimal:2',
    ];

    // Relationships
    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }

    public function maintenanceLog()
    {
        return $this->belongsTo(MaintenanceLog::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    // Scopes
    public function scopeForMachine($query, $machineId)
    {
        return $query->where('machine_id', $machineId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('used_at', '>=', now()->subDays($days));
    }

    // Get total cost for this usage
    public function getTotalCost(): float
    {
        return (float) ($this->quantity_used * ($this->cost_at_use ?? 0));
    }
}
