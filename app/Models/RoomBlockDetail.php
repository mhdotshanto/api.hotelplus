<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBlockDetail extends Model
{
    protected $guarded = ["id"];

    public function roomBlock()
    {
        return $this->belongsTo(RoomBlock::class);
    }
}
