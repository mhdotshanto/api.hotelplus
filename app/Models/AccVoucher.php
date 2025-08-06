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
 */

class AccVoucher extends Model
{
    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->receipt_ref)) {
                $model->receipt_ref = self::generateReceiptRef();
            }
        });
    }

    public static function generateReceiptRef($minlength = 8, $prefix = 'MR-'): string
    {
        $receiptRef = $prefix . rand(111111, 999999);
        if (self::where("receipt_ref", $receiptRef)->exists()) {
            return self::generateReceiptRef($minlength, $prefix);
        }
        return $receiptRef;
    }
}
