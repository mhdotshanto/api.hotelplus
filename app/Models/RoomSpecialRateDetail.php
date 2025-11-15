<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomSpecialRateDetail extends Model
{
    protected $guarded = ['id'];

    public function special_rate()
    {
        return $this->belongsTo(RoomSpecialRate::class);
    }

    public function categories()
    {
        return $this->belongsToMany(RoomCategory::class);
    }
}
