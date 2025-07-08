<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $guarded = ['id'];

    public function preferences()
    {
        return $this->belongsToMany(Preference::class, 'guest_preferences')->withTimestamps();
    }
}
