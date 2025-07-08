<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomHouseKeeping extends Model
{
    protected $fillable = [
        'room_number_id',
        'note',
    ];

    public function room()
    {
        return $this->belongsTo(RoomNumber::class);
    }
}
