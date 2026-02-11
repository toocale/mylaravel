<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $guarded = [];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
    
    public function machines()
    {
        return $this->belongsToMany(Machine::class, 'machine_shift')->withPivot('is_active');
    }
}
