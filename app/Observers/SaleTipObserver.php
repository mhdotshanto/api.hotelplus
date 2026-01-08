<?php

namespace App\Observers;

use App\Models\SaleTip;
use Illuminate\Support\Facades\DB;

class SaleTipObserver
{
    function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

    /**
     * Handle the SaleTip "created" event.
     */
    public function created(SaleTip $saleTip): void
    {
        DB::transaction(function () use ($saleTip) {
            $saleRoom = $saleTip->saleRoom()->lockForUpdate()->first();
            $saleRoom->increment('tip_amount', $saleTip->amount);
            $saleRoom->refresh();
            $price_sale = $saleRoom->price_sale - $saleRoom->adjusted_amount;
            $tipPercentage = $saleRoom->price_sale > 0 ? $this->fixDecimal(($saleRoom->tip_amount / $price_sale) * 100) : 0;
            $saleRoom->update(['tip_per' => $tipPercentage]);
        });
    }

    /**
     * Handle the SaleTip "updated" event.
     */
    public function updated(SaleTip $saleTip): void
    {
        //
    }

    /**
     * Handle the SaleTip "deleted" event.
     */
    public function deleted(SaleTip $saleTip): void
    {
        //
    }

    /**
     * Handle the SaleTip "restored" event.
     */
    public function restored(SaleTip $saleTip): void
    {
        //
    }

    /**
     * Handle the SaleTip "force deleted" event.
     */
    public function forceDeleted(SaleTip $saleTip): void
    {
        //
    }
}
