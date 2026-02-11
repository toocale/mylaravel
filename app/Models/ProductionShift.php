<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionShift extends Model
{
    protected $fillable = [
        'machine_id',
        'user_id',
        'product_id',
        'shift_id',
        'user_group',
        'started_at',
        'ended_at',
        'status',
        'good_count',
        'reject_count',
        'total_count',
        'metadata',
        'edited_by',
        'edited_at',
        'batch_number',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'edited_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function downtimeEvents()
    {
        return $this->hasMany(DowntimeEvent::class);
    }
    
    public function materialLosses()
    {
        return $this->hasMany(MaterialLoss::class, 'shift_id');
    }
    
    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Get the duration of the shift in hours
     */
    public function getDurationHours(): ?float
    {
        if (!$this->ended_at || !$this->started_at) {
            return null;
        }
        
        return $this->started_at->diffInHours($this->ended_at, true);
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function booted()
    {
        // When a shift is updated (ended), increment component runtime
        static::updated(function ($shift) {
            // Only increment if shift was just ended
            if ($shift->wasChanged('ended_at') && $shift->ended_at && $shift->machine_id) {
                static::incrementComponentRuntime($shift);
            }
        });
    }

    /**
     * Increment runtime hours for all components of this machine
     */
    protected static function incrementComponentRuntime($shift)
    {
        $durationHours = $shift->getDurationHours();
        
        if (!$durationHours || $durationHours <= 0) {
            return;
        }

        // Update all components for this machine
        MachineComponent::where('machine_id', $shift->machine_id)
            ->whereNotIn('status', ['replaced', 'removed'])
            ->increment('current_runtime_hours', $durationHours);

        // Log the update
        \Log::info("Auto-incremented component runtime for machine {$shift->machine_id} by {$durationHours} hours");
    }
    
    public function productChangeovers()
    {
        return $this->hasMany(ProductChangeover::class);
    }
    
    /**
     * Scope for active shifts only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Get the active shift for a specific machine
     */
    public static function getActiveForMachine(int $machineId): ?self
    {
        return static::where('machine_id', $machineId)
            ->where('status', 'active')
            ->with(['user', 'shift'])
            ->first();
    }
}
