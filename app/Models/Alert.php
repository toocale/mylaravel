<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'alert_rule_id',
        'machine_id',
        'severity',
        'title',
        'message',
        'data',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
    ];

    protected $casts = [
        'data' => 'array',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function alertRule()
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function acknowledgedByUser()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    // --- Helpers ---

    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    public function resolve(): void
    {
        $this->update([
            'resolved_at' => now(),
        ]);
    }

    public function isActive(): bool
    {
        return is_null($this->resolved_at);
    }
}
