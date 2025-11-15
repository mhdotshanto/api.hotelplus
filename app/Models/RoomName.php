<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomName extends Model
{
    protected $guarded = ["id"];

    public function category() {
        return $this->hasOne(RoomCategory::class, 'id', 'category_id');
    }

    public function special_rates() {
        return $this->hasMany(RoomSpecialRateDetail::class, 'room_name_id', 'id');
    }
}
