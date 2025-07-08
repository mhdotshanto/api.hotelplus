<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{
    protected $fillable = [
        'name',
        'rate',
        'rate_xb',
        'is_active'
    ];
}
