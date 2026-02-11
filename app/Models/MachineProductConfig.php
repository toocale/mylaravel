<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineProductConfig extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ideal_rate' => 'float',
    ];
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
