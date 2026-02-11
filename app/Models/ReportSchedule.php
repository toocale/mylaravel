<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'report_type',
        'frequency',
        'send_time',
        'recipients',
        'plant_id',
        'line_id',
        'machine_id',
        'is_active',
        'last_sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Get the user that created this schedule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plant for this schedule.
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Get the line for this schedule.
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Get the machine for this schedule.
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Scope active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get human-readable frequency.
     */
    public function getFrequencyLabelAttribute(): string
    {
        return match($this->frequency) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'shift_end' => 'End of Shift',
            default => ucfirst($this->frequency),
        };
    }

    /**
     * Get human-readable report type.
     */
    public function getReportTypeLabelAttribute(): string
    {
        return match($this->report_type) {
            'shift' => 'Shift Report',
            'daily_oee' => 'Daily OEE Summary',
            'downtime' => 'Downtime Analysis',
            'production' => 'Production Summary',
            default => ucfirst(str_replace('_', ' ', $this->report_type)),
        };
    }
}
