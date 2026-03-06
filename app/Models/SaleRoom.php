<?php

namespace App\Models;

use App\Observers\SaleRoomObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property mixed $id
 * @property mixed $price_sale
 * @property bool|mixed $is_confirmed
 * @property mixed $code
 * @property mixed|string $status
 * @property mixed $adjusted_amount
 * @property mixed $discount_note
 * @property mixed $user_id
 * @property mixed $guest_id0
 */
class SaleRoom extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::observe(SaleRoomObserver::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($saleRoom) {
            if (empty($saleRoom->code)) {
                $saleRoom->code = self::generateUniqueCode();
            }
            if($saleRoom->code){
                $saleRoom->token = encrypt($saleRoom->code);
            }
        });
    }

    public static function generateUniqueCode()
    {
        $lastRecord = self::orderBy('id', 'desc')->first();
        if (!$lastRecord || !$lastRecord->code) {
            return 'A00001';
        }
        $lastNumber = (int) substr($lastRecord->code, 1);
        $newNumber = $lastNumber + 1;
        return 'A' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    public function room_categories()
    {
        return $this->hasMany(SaleRoomCategory::class,'sale_id', 'id');
    }

    public function sale_room_allocation()
    {
        return $this->hasMany(SaleRoomAllocation::class, 'room_sale_id', 'id');
    }

    public function sale_tip()
    {
        return $this->hasMany(SaleTip::class, 'sale_room_id', 'id');
    }

    public function masterRoom()
    {
        return $this->belongsTo(RoomNumber::class, 'master_room')->select(['id', 'name']);
    }

    public function preferences()
    {
        return $this->belongsToMany(
            Preference::class,
            'sale_preferences',
            'sale_id',
            'preference_id'
        )->withTimestamps();
    }

    public function sale_guest()
    {
        return $this->hasOne(Guest::class, 'id', 'guest_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function sale_user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function sale_agent()
    {
        return $this->hasOne(User::class, 'id', 'ref_id');
    }

    public function note()
    {
        return $this->hasOne(SaleCancellationNotes::class, 'sale_id', 'id');
    }

    public function ScopeInhouseGuests($query){
        $today = now()->toDateString();
        return $query->where('status','checkedin')
                ->where('date_checkin','<=',$today)
                ->where('date_checkout','>=',$today);
    }

    public function ScopeCheckinMissedGuests($query){
        $today = now()->toDateString();
        return $query->whereIn('status', ['tentative', 'guaranteed'])
                ->where('date_checkin','<=',Carbon::yesterday()->toDateString());
    }
    public function ScopeCheckoutMissedGuests($query)
    {
        $today = now()->toDateString();
        return $query->where('status', '!=', 'checkout')
            ->where('date_checkout', '<', $today);
    }
    /*To Checkin Guests*/
    public function scopeOnArrivalGuests($query)
    {
        return $query->whereIn('status', ['tentative', 'guaranteed'])
            ->whereIn('date_checkin', [
                Carbon::today()->format('Y-m-d'),
                Carbon::yesterday()->format('Y-m-d'),
            ]);
    }
    /*To Checkout Guests*/
    public function scopeOnDepartureGuests($query)
    {
        return $query->where('status', 'checkedin')
            ->where('date_checkout', '<', Carbon::today()->format('Y-m-d'));
    }
    public function saleVouchers()
    {
        return $this->hasMany(AccVoucher::class, 'sale_room_id', 'id');
    }

    public function saleLedgers()
    {
        return $this->hasMany(AccLedger::class, 'sale_room_id')
            ->where('type', 'sale');
    }
}
