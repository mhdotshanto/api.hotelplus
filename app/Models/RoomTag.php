<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RoomTag extends Model
{
    protected $fillable = [
        'name',
        'icon',
    ];

    // Accessor for full icon URL
    public function getIconAttribute($value)
    {
        return $value ? asset($value) : null;
    }
}
