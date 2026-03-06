<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTemp extends Model
{
    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(RoomTempDetail::class, 'room_temp_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
