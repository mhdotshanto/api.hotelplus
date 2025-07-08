<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomSpecialRateDetail extends Model
{
    protected $fillable = [
        'special_rate_id',
        'category_id',
        'rate',
        'rate_xb',
    ];

    public function specialRate()
    {
        return $this->belongsTo(RoomSpecialRate::class);
    }

    public function categories()
    {
        return $this->belongsToMany(RoomCategory::class);
    }
}
