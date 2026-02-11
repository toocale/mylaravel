<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DailyOeeMetric extends Model
{
    protected $guarded = [];
    
    protected $appends = ['oee', 'availability', 'performance', 'quality', 'good_count', 'reject_count', 'total_count', 'downtime_minutes'];
    
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
    
    // Accessors to map database columns to expected attributes
    protected function oee(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->attributes['oee_score'] ?? 0, 2),
        );
    }
    
    protected function availability(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->attributes['availability_score'] ?? 0, 2),
        );
    }
    
    protected function performance(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->attributes['performance_score'] ?? 0, 2),
        );
    }
    
    protected function quality(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->attributes['quality_score'] ?? 0, 2),
        );
    }
    
    protected function goodCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['total_good'] ?? 0,
        );
    }
    
    protected function rejectCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['total_reject'] ?? 0,
        );
    }
    
    protected function totalCount(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->attributes['total_good'] ?? 0) + ($this->attributes['total_reject'] ?? 0),
        );
    }
    
    protected function downtimeMinutes(): Attribute
    {
        return Attribute::make(
            get: fn () => round(($this->attributes['total_downtime'] ?? 0) / 60, 2),
        );
    }
}
