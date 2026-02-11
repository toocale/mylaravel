<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DowntimeEvent extends Model
{
    protected $guarded = [];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function reasonCode()
    {
        return $this->belongsTo(ReasonCode::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function productionShift()
    {
        return $this->belongsTo(ProductionShift::class);
    }
}
