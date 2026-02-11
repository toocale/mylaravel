<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'group_permission');
    }
}
