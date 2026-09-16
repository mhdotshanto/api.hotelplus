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

    public function head(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccHead::class, 'acc_head_id', 'id');
    }

    public function bank(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccBank::class, 'bank_id', 'id');
    }

    public function party(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccParty::class, 'party_id', 'id');
    }

    public function ledgerNote(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccNote::class, 'note_id', 'id');
    }
    public function voucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccVoucher::class, 'voucher_id', 'id');
    }
}
