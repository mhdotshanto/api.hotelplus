<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccLedger extends Model
{
    protected $guarded = ['id'];

    public function head() {
        return $this->hasOne(AccHead::class, 'id', 'acc_head_id');
    }
}
