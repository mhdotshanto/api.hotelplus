<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// room qty will be always 1 but extra bed qty can be more than 1 so qty field will always represent extra bed qty
class SaleRoomAllocation extends Model
{
    protected $guarded = ['id'];

    public function room()
    {
        return $this->belongsTo(RoomNumber::class, 'room_number_id');
    }

    public function saleRoom()
    {
        return $this->belongsTo(SaleRoom::class, 'room_sale_id');
    }

    public function room_name()
    {
        return $this->hasOne(RoomName::class, 'id', 'room_name_id');
    }
}
