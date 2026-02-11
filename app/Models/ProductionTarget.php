<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionTarget extends Model
{
    protected $fillable = [
        'machine_id',
        'line_id',
        'shift_id',
        'effective_from',
        'effective_to',
        'target_oee',
        'target_availability',
        'target_performance',
        'target_quality',
        'target_units',
        'target_good_units',
        'created_by',
        'updated_by',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'target_oee' => 'decimal:2',
        'target_availability' => 'decimal:2',
        'target_performance' => 'decimal:2',
        'target_quality' => 'decimal:2',
        'target_units' => 'integer',
        'target_good_units' => 'integer',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get active targets for a specific date
     */
    public function scopeActive($query, $date = null)
    {
        $date = $date ?? now()->toDateString();
        
        return $query->whereRaw('DATE(effective_from) <= ?', [$date])
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhereRaw('DATE(effective_to) >= ?', [$date]);
            });
    }

    /**
     * Scope to get targets for a specific machine
     */
    public function scopeForMachine($query, int $machineId)
    {
        return $query->where('machine_id', $machineId);
    }

    /**
     * Scope to get targets for a specific shift
     */
    public function scopeForShift($query, int $shiftId = null)
    {
        if ($shiftId) {
            return $query->where(function ($q) use ($shiftId) {
                $q->where('shift_id', $shiftId)
                  ->orWhereNull('shift_id'); // Include "all shifts" targets
            });
        }
        
        return $query->whereNull('shift_id');
    }

    /**
     * Get the most specific applicable target for a machine and shift
     */
    public static function getApplicableTarget(int $machineId, int $shiftId = null, $date = null): ?self
    {
        $date = $date ?? now()->toDateString();
        
        // Priority 1: Shift-specific target
        if ($shiftId) {
            $target = static::forMachine($machineId)
                ->where('shift_id', $shiftId)
                ->active($date)
                ->orderBy('effective_from', 'desc')
                ->first();
                
            if ($target) {
                return $target;
            }
        }
        
        // Priority 2: All-shifts target for this machine
        return static::forMachine($machineId)
            ->whereNull('shift_id')
            ->active($date)
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Check if this target is currently active
     */
    public function isActive($date = null): bool
    {
        $date = $date ?? now()->toDateString();
        
        // Target hasn't started yet
        if ($this->effective_from && $this->effective_from->toDateString() > $date) {
            return false;
        }
        
        // Target has already ended
        if ($this->effective_to && $this->effective_to->toDateString() < $date) {
            return false;
        }
        
        return true;
    }

    /**
     * Get a formatted display of the target period
     */
    public function getPeriodDisplayAttribute(): string
    {
        $from = $this->effective_from->format('M d, Y');
        $to = $this->effective_to ? $this->effective_to->format('M d, Y') : 'Ongoing';
        
        return "{$from} - {$to}";
    }
}
