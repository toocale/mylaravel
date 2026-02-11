<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReasonCode extends Model
{
    protected $guarded = [];

    public function downtimeType()
    {
        return $this->belongsTo(DowntimeType::class);
    }
}
