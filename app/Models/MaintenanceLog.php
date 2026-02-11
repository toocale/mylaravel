<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'maintenance_schedule_id',
        'machine_id',
        'performed_by_user_id',
        'performed_at',
        'task_description',
        'duration_minutes',
        'notes',
        'parts_replaced',
        'cost',
        'next_scheduled_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'next_scheduled_at' => 'datetime',
        'parts_replaced' => 'array',
        'cost' => 'decimal:2',
    ];

    // Relationships
    public function maintenanceSchedule()
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    // Scopes
    public function scopeForMachine($query, $machineId)
    {
        return $query->where('machine_id', $machineId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('performed_at', '>=', now()->subDays($days));
    }

    // Spare parts used in this maintenance
    public function partsUsed()
    {
        return $this->hasMany(SparePartUsage::class);
    }

    // Get total parts cost for this log
    public function getTotalPartsCost(): float
    {
        return (float) $this->partsUsed()
            ->selectRaw('SUM(quantity_used * COALESCE(cost_at_use, 0)) as total')
            ->value('total') ?? 0;
    }
}
