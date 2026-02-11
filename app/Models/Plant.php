<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasProperCase;

class Plant extends Model
{
    use HasProperCase;
    
    protected $guarded = [];

    /**
     * Attributes that should be converted to proper case
     */
    protected $properCaseAttributes = ['name', 'description'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function lines()
    {
        return $this->hasMany(Line::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
