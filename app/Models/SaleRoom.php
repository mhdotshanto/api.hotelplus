<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleRoom extends Model
{
    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($saleRoom) {
            if (empty($saleRoom->code)) {
                $saleRoom->code = self::generateUniqueCode();
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

    public function sale_room_allocation()
    {
        return $this->hasMany(SaleRoomAllocation::class, 'room_sale_id', 'id');
    }

    public function sale_guest()
    {
        return $this->hasOne(Guest::class, 'id', 'guest_id');
    }
    public function sale_user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    public function sale_agent()
    {
        return $this->hasOne(User::class, 'id', 'ref_id');
    }
}
