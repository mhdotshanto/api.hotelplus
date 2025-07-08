<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestPreference extends Model
{
    protected $fillable = [
        'guest_id',
        'preference_id',
    ];
}
