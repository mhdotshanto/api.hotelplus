<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTempDetailXb extends Model
{
    protected $fillable = [
        'room_temp_id',
        'room_number_id',
        'room_category_id',
        'room_name_id',
        'date',
        'stock',
        'qty',
    ];

    public function category()
    {
        return $this->belongsTo(RoomCategory::class,'room_category_id');
    }

    public function room()
    {
        return $this->belongsTo(RoomNumber::class,'room_number_id');
    }

    public function roomName()
    {
        return $this->belongsTo(RoomName::class, 'room_name_id');
    }
}
