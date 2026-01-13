<?php

namespace App\Observers;

use App\Models\AccLedger;
use App\Models\SaleRoom;

class AccLedgerObserver
{
    function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

    /**
     * Handle the AccLedger "created" event.
     */
    public function created(AccLedger $accLedger): void
    {
        if($accLedger->type !== 'sale') return;

        $sale = SaleRoom::find($accLedger->sale_room_id);
        if (!$sale) return;

        $totalPaid = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $sale->id)
            ->sum('credit');

        $totalDue = max(0, $sale->price_net - $totalPaid);
        $sale->update(['total_due' => $totalDue]);
    }

    /**
     * Handle the AccLedger "updated" event.
     */
    public function updated(AccLedger $accLedger): void
    {
        //
    }

    /**
     * Handle the AccLedger "deleted" event.
     */
    public function deleted(AccLedger $accLedger): void
    {
        //
    }

    /**
     * Handle the AccLedger "restored" event.
     */
    public function restored(AccLedger $accLedger): void
    {
        //
    }

    /**
     * Handle the AccLedger "force deleted" event.
     */
    public function forceDeleted(AccLedger $accLedger): void
    {
        //
    }
}
