<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomName extends Model
{
    protected $guarded = ["id"];

    public function category() {
        return $this->hasOne(RoomCategory::class, 'id', 'category_id');
    }
}
