<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasProperCase;

class Machine extends Model
{
    use HasProperCase;
    
    protected $guarded = [];
    
    protected $properCaseAttributes = ['name', 'description'];

    protected $casts = [
        'default_ideal_rate' => 'float',
    ];

    public function line()
    {
        return $this->belongsTo(Line::class);
    }

    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function downtimeEvents()
    {
        return $this->hasMany(DowntimeEvent::class);
    }

    public function dailymetrics()
    {
        return $this->hasMany(DailyOeeMetric::class);
    }

    public function machineProductConfigs()
    {
        return $this->hasMany(MachineProductConfig::class);
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'machine_shift')->withPivot('is_active')->withTimestamps();
    }

    public function reasonCodes()
    {
        return $this->belongsToMany(ReasonCode::class, 'machine_reason_codes')->withTimestamps();
    }

    public function productionShifts()
    {
        return $this->hasMany(ProductionShift::class);
    }
}
