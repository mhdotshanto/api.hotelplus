<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBlockTempDetail extends Model
{
    protected $fillable = [
        'temp_id',
        'room_id',
        'date',
        'rate',
        'gross_rate',
        'sale_rate',
        'is_xb',
    ];
}
