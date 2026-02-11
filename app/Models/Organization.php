<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $guarded = [];

    public function plants()
    {
        return $this->hasMany(Plant::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function reasonCodes()
    {
        return $this->hasMany(ReasonCode::class);
    }
}
