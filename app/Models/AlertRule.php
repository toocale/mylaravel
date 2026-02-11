<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'severity',
        'threshold',
        'duration_minutes',
        'scope_type',
        'scope_id',
        'is_active',
        'notify_email',
        'cooldown_minutes',
    ];

    protected $casts = [
        'threshold' => 'float',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'notify_email' => 'boolean',
        'cooldown_minutes' => 'integer',
    ];

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this rule applies to a given machine.
     */
    public function appliesToMachine(Machine $machine): bool
    {
        if (!$this->scope_type || !$this->scope_id) {
            return true; // Global rule
        }

        return match ($this->scope_type) {
            'machine' => $machine->id === $this->scope_id,
            'line' => $machine->line_id === $this->scope_id,
            'plant' => $machine->line?->plant_id === $this->scope_id,
            default => true,
        };
    }

    /**
     * Check if this rule is in cooldown for a given machine.
     */
    public function isInCooldown(int $machineId): bool
    {
        return $this->alerts()
            ->where('machine_id', $machineId)
            ->where('triggered_at', '>=', now()->subMinutes($this->cooldown_minutes))
            ->exists();
    }
}
