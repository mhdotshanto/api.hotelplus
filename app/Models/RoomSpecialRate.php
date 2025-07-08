<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomSpecialRate extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'note',
    ];

    public function details()
    {
        return $this->hasMany(RoomSpecialRateDetail::class, 'special_rate_id');
    }
}
