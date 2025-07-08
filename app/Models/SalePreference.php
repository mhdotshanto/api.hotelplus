<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePreference extends Model
{
    protected $fillable = [
        'sale_id',
        'preference_id',
    ];
}
