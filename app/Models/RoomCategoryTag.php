<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCategoryTag extends Model
{
    protected $fillable = [
        'category_id',
        'tag_id',
    ];

    public function category()
    {
        return $this->belongsTo(RoomCategory::class, 'category_id');
    }

    public function tag()
    {
        return $this->belongsTo(RoomTag::class, 'tag_id');
    }
}
