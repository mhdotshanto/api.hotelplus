<?php

namespace App\Observers;

use App\Models\AccLedger;
use App\Models\AccVoucher;
use App\Models\SaleRoom;

class AccLedgerObserver
{
    function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

    public function syncSaleRoomTotals(?int $saleRoomId): void
    {
        if (!$saleRoomId) return;
        $sale = SaleRoom::find($saleRoomId);
        if (!$sale) return;

        $payments = (float) (AccVoucher::where('sale_room_id', $sale->id)
            ->where('type', 'payment')
            ->sum('amount') ?? 0);

        $refunds = (float) (AccVoucher::where('sale_room_id', $sale->id)
            ->where('type', 'refund')
            ->sum('amount') ?? 0);

        $adjustments = (float) (AccVoucher::where('sale_room_id', $sale->id)
            ->whereIn('type', ['adjustment', 'discount'])
            ->sum('amount') ?? 0);

        $netPaid = max(0, $payments - $refunds);
        $totalDue = max(0, (float) $sale->price_net + $refunds - $payments - $adjustments);

        $sale->updateQuietly([
            'total_due'  => $totalDue,
            'total_paid' => $netPaid
        ]);
    }

    /**
     * Handle the AccLedger "created" event.
     */
    public function created(AccLedger $accLedger): void
    {
        if($accLedger->type !== 'sale') return;
        $this->syncSaleRoomTotals($accLedger->sale_room_id);
    }

    /**
     * Handle the AccLedger "updated" event.
     */
    public function updated(AccLedger $accLedger): void
    {
        if($accLedger->type !== 'sale') return;
        $this->syncSaleRoomTotals($accLedger->sale_room_id);
    }

    /**
     * Handle the AccLedger "deleted" event.
     */
    public function deleted(AccLedger $accLedger): void
    {
        if($accLedger->type !== 'sale') return;
        $this->syncSaleRoomTotals($accLedger->sale_room_id);
    }

    /**
     * Handle the AccLedger "restored" event.
     */
    public function restored(AccLedger $accLedger): void
    {
        if($accLedger->type !== 'sale') return;
        $this->syncSaleRoomTotals($accLedger->sale_room_id);
    }

    /**
     * Handle the AccLedger "force deleted" event.
     */
    public function forceDeleted(AccLedger $accLedger): void
    {
        if($accLedger->type !== 'sale') return;
        $this->syncSaleRoomTotals($accLedger->sale_room_id);
    }
}
