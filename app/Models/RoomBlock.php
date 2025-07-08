<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBlock extends Model
{
    protected $guarded = ["id"];

    public function details()
    {
        return $this->hasMany(RoomBlockDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
