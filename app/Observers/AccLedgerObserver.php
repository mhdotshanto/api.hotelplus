<?php

namespace App\Observers;

use App\Enums\AccVoucherType;
use App\Models\AccLedger;
use App\Models\AccVoucher;
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
        $this->syncSaleRoomBalances($accLedger);
    }

    /**
     * Handle the AccLedger "updated" event.
     */
    public function updated(AccLedger $accLedger): void
    {
        $this->syncSaleRoomBalances($accLedger);
    }

    /**
     * Handle the AccLedger "deleted" event.
     */
    public function deleted(AccLedger $accLedger): void
    {
        $this->syncSaleRoomBalances($accLedger);
    }

    protected function syncSaleRoomBalances(AccLedger $accLedger): void
    {
        if ($accLedger->type !== 'sale') return;

        $sale = SaleRoom::find($accLedger->sale_room_id);
        if (!$sale) return;

        $saleLedger = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $sale->id)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalPayments = (float) (AccLedger::where('type', 'sale')
            ->where('sale_room_id', $sale->id)
            ->whereIn('voucher_id', function ($q) use ($sale) {
                $q->select('id')
                    ->from('acc_vouchers')
                    ->where('sale_room_id', $sale->id)
                    ->where('type', AccVoucherType::PAYMENT->value);
            })
            ->sum('credit') ?? 0);

        $totalRefund = (float) (AccVoucher::where('sale_room_id', $sale->id)
            ->where('type', AccVoucherType::REFUND->value)
            ->sum('amount') ?? 0);

        $totalCredit = (float) ($saleLedger->total_credit ?? 0);
        $totalDebit  = (float) ($saleLedger->total_debit ?? 0);

        $netPaid = $this->fixDecimal($totalPayments - $totalRefund);
        $totalDue = $this->fixDecimal($totalDebit - $totalCredit);

        $sale->updateQuietly([
            'total_due'  => $totalDue,
            'total_paid' => $netPaid
        ]);
    }

    /**
     * Handle the AccLedger "restored" event.
     */
    public function restored(AccLedger $accLedger): void
    {
        $this->syncSaleRoomBalances($accLedger);
    }

    /**
     * Handle the AccLedger "force deleted" event.
     */
    public function forceDeleted(AccLedger $accLedger): void
    {
        $this->syncSaleRoomBalances($accLedger);
    }
}
