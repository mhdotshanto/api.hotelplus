<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomNumber extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(RoomCategory::class);
    }

    public function roomName()
    {
        return $this->belongsTo(RoomName::class, 'room_name_id');
    }

    public function floor()
    {
        return $this->belongsTo(RoomFloor::class);
    }

    public function room_block_details()
    {
        return $this->hasMany(RoomBlockDetail::class,'room_id', 'id');
    }

    public function room_temp_details()
    {
        return $this->hasMany(RoomTempDetail::class, 'room_number_id', 'id');
    }

    public function room_sale_allocation()
    {
        return $this->hasMany(SaleRoomAllocation::class, 'room_number_id', 'id');
    }
}
