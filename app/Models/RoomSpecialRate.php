<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomSpecialRate extends Model
{
    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(RoomSpecialRateDetail::class, 'special_rate_id');
    }
}
