<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// room qty will be always 1 but extra bed qty can be more than 1 so qty field will always represent extra bed qty
class RoomTempDetail extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(RoomCategory::class,'room_category_id','id');
    }

    public function room()
    {
        return $this->belongsTo(RoomNumber::class,'room_number_id');
    }

    public function roomNumber()
    {
        return $this->belongsTo(RoomNumber::class,'room_number_id');
    }

    public function roomName()
    {
        return $this->belongsTo(RoomName::class, 'room_name_id');
    }

    public function room_temp()
    {
        return $this->hasOne(RoomTemp::class, 'id', 'room_temp_id');
    }

    public function room_xb() {
        return $this->hasOne(RoomTempDetailXb::class, 'room_temp_id', 'room_temp_id');
    }
}

