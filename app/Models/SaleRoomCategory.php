<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleRoomCategory extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(RoomCategory::class,'category_id');
    }
}

