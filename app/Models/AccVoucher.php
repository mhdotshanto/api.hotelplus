<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $receipt_ref
 * @property int $acc_head_id
 * @property int $acc_bank_id
 * @property int $sale_room_id
 * @property string|null $note
 * @property string|null $reference_number
 * @property string $payment_method // e.g., 'cash', 'bank'
 * @property string $status // 'pending' or 'paid'
 * @property float $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $acc_party_id
 * @property mixed $type
 */

class AccVoucher extends Model
{
    protected $guarded = ['id'];

    public static function generateReceiptRef($minlength = 8, $prefix = 'MR-'): string
    {
        $receiptRef = $prefix . rand(111111, 999999);
        if (self::where("receipt_ref", $receiptRef)->exists()) {
            return self::generateReceiptRef($minlength, $prefix);
        }
        return $receiptRef;
    }

    public function ledgers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccLedger::class, 'voucher_id', 'id');
    }


    public function saleRoom()
    {
        return $this->belongsTo(SaleRoom::class, 'sale_room_id');
    }
    public function bank()
    {
        return $this->belongsTo(AccBank::class, 'acc_bank_id');
    }
    public function party()
    {
        return $this->belongsTo(AccParty::class, 'acc_party_id');
    }
    public function head()
    {
        return $this->belongsTo(AccHead::class, 'acc_head_id');
    }
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
     public function tip(){
        $this->hasOne(SaleTip::class,sale_room_id);
    }
}
