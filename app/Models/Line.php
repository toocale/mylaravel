<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasProperCase;

class Line extends Model
{
    use HasProperCase;
    
    protected $guarded = [];
    
    protected $properCaseAttributes = ['name', 'description'];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }
}
