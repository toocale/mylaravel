<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    protected $fillable = [
        'machine_id',
        'task_name',
        'description',
        'maintenance_type',
        'frequency_days',
        'frequency_hours',
        'frequency_cycles',
        'last_performed_at',
        'next_due_at',
        'is_overdue',
        'priority',
        'estimated_duration_minutes',
        'assigned_to_user_id',
        'is_active',
    ];

    protected $casts = [
        'last_performed_at' => 'datetime',
        'next_due_at' => 'datetime',
        'is_overdue' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    // Scopes
    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true);
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->where('next_due_at', '<=', now()->addDays($days))
                    ->where('next_due_at', '>=', now());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
