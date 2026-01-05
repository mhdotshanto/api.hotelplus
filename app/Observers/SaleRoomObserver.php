<?php

namespace App\Observers;

use App\Models\SaleRoom;
class SaleRoomObserver
{
    function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

    protected function updateSaleRoomCategories(SaleRoom $saleRoom): void
    {
        $tipPer = (float) $saleRoom->tip_per;
        $saleRoom->loadMissing('roomCategories');
        $saleRoom->roomCategories->each(function ($category) use ($tipPer) {
            $tipAmount = $this->fixDecimal(($category->rate_sale * $tipPer) / 100);
            $category->updateQuietly([ 'tip_amount' => $tipAmount ]);
        });
    }

    /**
     * Handle the SaleRoom "created" event.
     */
    public function created(SaleRoom $saleRoom): void
    {
        //
    }

    /**
     * Handle the SaleRoom "updated" event.
     */
    public function updated(SaleRoom $saleRoom): void
    {
        if ($saleRoom->wasChanged(['tip_per', 'tip_amount'])) $this->updateSaleRoomCategories($saleRoom);
    }

    /**
     * Handle the SaleRoom "deleted" event.
     */
    public function deleted(SaleRoom $saleRoom): void
    {
        //
    }

    /**
     * Handle the SaleRoom "restored" event.
     */
    public function restored(SaleRoom $saleRoom): void
    {
        //
    }

    /**
     * Handle the SaleRoom "force deleted" event.
     */
    public function forceDeleted(SaleRoom $saleRoom): void
    {
        //
    }
}
