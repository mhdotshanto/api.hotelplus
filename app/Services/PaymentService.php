<?php

namespace App\Services;

use App\Models\AccHead;
use App\Models\AccLedger;
use App\Models\AccVoucher;
use App\Models\SaleRoom;
use Illuminate\Support\Carbon;

class PaymentService
{
    protected $saleRoomHeadId;
    protected $reservationPaymentHeadId;

    public function __construct()
    {
        $saleRoomHead                     = AccHead::where('slug','sale-room')->select('id')->firstOrFail();
        $reservationPaymentHead           = AccHead::where('slug','reservation-payment')->select('id')->firstOrFail();
        $this->saleRoomHeadId             = $saleRoomHead->id;
        $this->reservationPaymentHeadId   = $reservationPaymentHead->id;
    }

    //insert package sale ledger entry method
    public function packageSaleLedgerEntry(
        AccVoucher $voucher,
        string $type,
        string $accType,
    ){
        return AccLedger::create([
            'voucher_id'        => $voucher->id,
            'sale_room_id'      => $voucher->sale_room_id,
            'acc_head_id'       => $accType == 'acc_head' ? $this->saleRoomHeadId : 0,
            'date'              => Carbon::now(),
            'type'              => $type,
            'acc_type'          => $accType,
            'is_cash'           => true,
            'debit'             => $voucher->amount,
        ]);
    }

    //insert package sale cash book entry method
    public function insertPackageSaleCashBookEntry(
        AccVoucher $voucher,
        string $accType
    ) {
        AccLedger::create([
            'voucher_id'        => $voucher->id,
            'sale_room_id'      => $voucher->sale_room_id,
            'acc_head_id'       => $accType == 'acc_head' ? $this->saleRoomHeadId : 0,
            'date'              => Carbon::now(),
            'type'              => 'cashbook',
            'acc_type'          => $accType,
            'is_cash'           => false,
            'debit'             => $accType == 'acc_head' ? $voucher->amount : 0.00,
            'credit'            => $accType == 'sale' ? $voucher->amount : 0.00,
        ]);
    }

    public function confirmReservationAndAssociatedLedgerEntries(SaleRoom $saleRoom)
    {
        //reservation billing voucher entry
        $billingVoucher = $this->reservationBillingVoucherEntry($saleRoom);

        //cashbook debit side entry (acc_type:acc_head)
        $this->insertPackageSaleCashBookEntry(
            $billingVoucher,
            'acc_head'
        );

        //cashbook credit side entry (acc_type:sale)
        $this->insertPackageSaleCashBookEntry(
            $billingVoucher,
            'sale'
        );

        //sale ledger entry (acc_type:sale)
        $this->packageSaleLedgerEntry(
            $billingVoucher,
            'sale',
            'sale'
        );

        //general ledger entry (acc_type:acc_head)
        $this->packageSaleLedgerEntry(
            $billingVoucher,
            'general ',
            'acc_head'
        );

        $saleRoom->is_confirmed = true;
        $saleRoom->save();
    }

    //insert reservation payment voucher entry
    public function reservationPaymentVoucherEntry(
        int $saleId,
        int | null $platformId,
        int | null $partyId,
        string | null $note,
        string | null $referenceNumber,
        string $paymentMethod,
        string $amount,
    ) {
        return AccVoucher::create([
            "acc_head_id"       => $this->reservationPaymentHeadId,
            "acc_bank_id"       => $platformId??0,
            "sale_room_id"      => $saleId,
            "acc_party_id"      => $partyId??0,
            "type"              => "payment",
            "note"              => $note,
            "reference_number"  => $referenceNumber,
            "payment_method"    => $paymentMethod,
            "amount"            => $amount,
        ]);
    }

    //insert reservation billing voucher entry
    public function reservationBillingVoucherEntry(SaleRoom $saleRoom) {
        return AccVoucher::create([
            'receipt_ref'           => $saleRoom->code,
            'acc_head_id'           => $this->saleRoomHeadId,
            'acc_bank_id'           => 0,
            'sale_room_id'          => $saleRoom->id,
            'type'                  => "bill",
            'payment_method'        => "N/A",
            'status'                => "N/A",
            'amount'                => $saleRoom->price_sale,
        ]);
    }

    public function reservationPaymentCashBookEntry(AccVoucher $voucher){
        // cashbook entry for (acc_type:sale) debit side
        AccLedger::create([
            'voucher_id'       => $voucher->id,
            'sale_room_id'     => $voucher->sale_room_id,
            'bank_id'          => $voucher->acc_bank_id,
            'party_id'         => $voucher->acc_party_id,
            'date'             => Carbon::now(),
            'type'             => 'cashbook',
            'acc_type'         => 'sale',
            'note'             => $voucher->note,
            'payment_method'   => $voucher->payment_method,
            'is_cash'          => $voucher->payment_method === 'cash',
            'reference_number' => $voucher->reference_number,
            'debit'            => $voucher->amount,
        ]);

        // if bank payment then cashbook entry (acc_type:bank) credit side
        if ($voucher->payment_method == 'bank') {
            AccLedger::create([
                'voucher_id'       => $voucher->id,
                'sale_room_id'     => $voucher->sale_room_id,
                'bank_id'          => $voucher->acc_bank_id,
                'party_id'          => $voucher->acc_party_id,
                'date'             => Carbon::now(),
                'type'             => 'cashbook',
                'acc_type'         => 'bank',
                'note'             => $voucher->note,
                'payment_method'   => $voucher->payment_method,
                'reference_number' => $voucher->reference_number,
                'credit'           => $voucher->amount,
            ]);
        }

        // if party-due then cashbook entry (acc_type:party) credit side
        if ($voucher->payment_method == 'party') {
            AccLedger::create([
                'voucher_id'       => $voucher->id,
                'sale_room_id'     => $voucher->sale_room_id,
                'bank_id'          => $voucher->acc_bank_id,
                'party_id'          => $voucher->acc_party_id,
                'date'             => Carbon::now(),
                'type'             => 'cashbook',
                'acc_type'         => 'party',
                'note'             => $voucher->note,
                'payment_method'   => $voucher->payment_method,
                'reference_number' => $voucher->reference_number,
                'credit'           => $voucher->amount,
            ]);
        }
    }

    public function ledgerEntry(
        AccVoucher $voucher,
        string $type,
        string $accType
    ) {
        return AccLedger::create([
            'voucher_id'        => $voucher->id,
            'sale_room_id'      => $voucher->sale_room_id,
            'acc_head_id'       => $accType == 'acc_head' ? $voucher->acc_head_id : 0,
            'bank_id'           => $accType == 'bank' ? $voucher->acc_bank_id : 0,
            'party_id'          => $accType == 'party' ? $voucher->acc_party_id : 0,
            'date'              => Carbon::now(),
            'type'              => $type,
            'acc_type'          => $accType,
            'note'              => $voucher->note,
            'is_cash'           => true,
            'reference_number'  => $voucher->reference_number,
            'debit'             => ($accType == 'bank' || $accType == 'party') ? $voucher->amount : 0.00,
            'credit'            => ($accType != 'bank' && $accType != 'party') ? $voucher->amount : 0.00,
        ]);
    }

    public function reservationPaymentLedgersEntry(AccVoucher $voucher) {
        //sale ledger entry (acc_type:sale)
        $this->ledgerEntry(
            $voucher,
            'sale',
            'sale'
        );

        //general ledger entry (acc_type:acc_head)
        $this->ledgerEntry(
            $voucher,
            'general',
            'acc_head'
        );

        //if payment method is bank then insert a bank ledger (acc_type:bank)
        if($voucher->payment_method != 'cash' && $voucher->payment_method != 'party-due') {
            $this->ledgerEntry(
                $voucher,
                'bank',
                'bank'
            );
        }

        //if payment method is party-due then insert a party ledger (acc_type:party)
        if($voucher->payment_method == 'party') {
            $this->ledgerEntry(
                $voucher,
                'party',
                'party'
            );
        }
        $voucher->status = 'paid';
        $voucher->save();
    }
}
