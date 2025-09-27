<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleTip extends Model
{
    protected $guarded = ['id'];
    public function tipVoucher(){
        $this->hasOne(AccVoucher::class,sale_room_id);
    }
}
