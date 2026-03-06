<?php

namespace App\Models;

use App\Observers\SaleTipObserver;
use Illuminate\Database\Eloquent\Model;

class SaleTip extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::observe(SaleTipObserver::class);
    }

//    public function tipVoucher(){
//        $this->hasOne(AccVoucher::class,sale_room_id);
//    }

    public function saleRoom() {
        return $this->belongsTo(SaleRoom::class, 'sale_room_id');
    }
}
