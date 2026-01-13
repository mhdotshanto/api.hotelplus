<?php

namespace App\Models;

use App\Observers\AccLedgerObserver;
use Illuminate\Database\Eloquent\Model;

class AccLedger extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::observe(AccLedgerObserver::class);
    }

    public function head() {
        return $this->hasOne(AccHead::class, 'id', 'acc_head_id');
    }
    public function ledgerNote(){
        return $this->hasOne(AccNote::class,'id','note_id');
    }
    public function voucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccVoucher::class, 'voucher_id', 'id');
    }
}
