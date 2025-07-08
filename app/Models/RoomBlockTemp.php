<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBlockTemp extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'last_active',
        'discount',
    ];
}
