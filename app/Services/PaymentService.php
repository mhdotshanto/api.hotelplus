<?php

namespace App\Services;

use App\Models\AccHead;
use App\Models\AccLedger;
use App\Models\AccNote;
use App\Models\AccVoucher;
use App\Models\SaleRoom;
use Illuminate\Support\Carbon;

class PaymentService
{
    protected int $saleRoomHeadId;
    protected int $reservationPaymentHeadId;
    protected int $refundAmountHeadId;
    protected int $adjustAmountHeadId;
    protected int $discountHeadId;

    public function __construct()
    {
        $saleRoomHead                       = AccHead::where('slug','sale-room')->select('id')->firstOrFail();
        $reservationPaymentHead             = AccHead::where('slug','reservation-payment')->select('id')->firstOrFail();
        $refundAmountHead                   = AccHead::where('slug','refund-amount')->select('id')->firstOrFail();
        $adjustAmountHead                   = AccHead::where('slug','adjust-amount')->select('id')->firstOrFail();
        $discountHead                       = AccHead::where('slug','reservation-payment')->select('id')->firstOrFail();

        $this->saleRoomHeadId               = $saleRoomHead->id;
        $this->reservationPaymentHeadId     = $reservationPaymentHead->id;
        $this->refundAmountHeadId           = $refundAmountHead->id;
        $this->adjustAmountHeadId           = $adjustAmountHead->id;
        $this->discountHeadId               = $discountHead->id;
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

    //insert package sale cashbook entry method
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
        int | null $receiverId,
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
            "receiver_id"       => $receiverId??0,
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

    //insert reservation refund voucher entry
    public function reservationRefundVoucherEntry(
        int $saleId,
        int | null $platformId,
        int | null $partyId,
        int | null $receiverId,
        string | null $note,
        string | null $referenceNumber,
        string $paymentMethod,
        string $amount,
    ) {
        return AccVoucher::create([
            "acc_head_id"       => $this->refundAmountHeadId,
            "acc_bank_id"       => $platformId??0,
            "sale_room_id"      => $saleId,
            "acc_party_id"      => $partyId??0,
            "receiver_id"       => $receiverId??0,
            "type"              => "refund",
            "note"              => $note,
            "reference_number"  => $referenceNumber,
            "payment_method"    => $paymentMethod,
            "amount"            => $amount,
        ]);
    }

    //insert reservation payment cashbook entry
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
                'payment_method'   => $voucher->payment_method,
                'reference_number' => $voucher->reference_number,
                'credit'           => $voucher->amount,
            ]);
        }

        // if party then cashbook entry (acc_type:party) credit side
        if ($voucher->payment_method == 'party') {
            AccLedger::create([
                'voucher_id'       => $voucher->id,
                'sale_room_id'     => $voucher->sale_room_id,
                'bank_id'          => $voucher->acc_bank_id,
                'party_id'          => $voucher->acc_party_id,
                'date'             => Carbon::now(),
                'type'             => 'cashbook',
                'acc_type'         => 'party',
                'payment_method'   => $voucher->payment_method,
                'reference_number' => $voucher->reference_number,
                'credit'           => $voucher->amount,
            ]);
        }
    }

    //insert reservation refund cashbook entry
    public function reservationRefundCashbookEntry(AccVoucher $voucher) {
        // cashbook entry for refund amount (acc_type:sale) credit side
        AccLedger::create([
            'voucher_id'       => $voucher->id,
            'sale_room_id'     => $voucher->sale_room_id,
            'bank_id'          => $voucher->acc_bank_id,
            'party_id'         => $voucher->acc_party_id,
            'date'             => Carbon::now(),
            'type'             => 'cashbook',
            'acc_type'         => 'sale',
            'payment_method'   => $voucher->payment_method,
            'is_cash'          => $voucher->payment_method === 'cash',
            'reference_number' => $voucher->reference_number,
            'credit'           => $voucher->amount,
        ]);

        // if bank payment then cashbook entry for refund amount (acc_type:bank) debit side
        if ($voucher->payment_method == 'bank') {
            AccLedger::create([
                'voucher_id'       => $voucher->id,
                'sale_room_id'     => $voucher->sale_room_id,
                'bank_id'          => $voucher->acc_bank_id,
                'party_id'          => $voucher->acc_party_id,
                'date'             => Carbon::now(),
                'type'             => 'cashbook',
                'acc_type'         => 'bank',
                'payment_method'   => $voucher->payment_method,
                'reference_number' => $voucher->reference_number,
                'debit'            => $voucher->amount,
            ]);
        }

        // if party then cashbook entry  for refund amount (acc_type:party) debit side
        if ($voucher->payment_method == 'party') {
            AccLedger::create([
                'voucher_id'       => $voucher->id,
                'sale_room_id'     => $voucher->sale_room_id,
                'bank_id'          => $voucher->acc_bank_id,
                'party_id'         => $voucher->acc_party_id,
                'date'             => Carbon::now(),
                'type'             => 'cashbook',
                'acc_type'         => 'party',
                'payment_method'   => $voucher->payment_method,
                'reference_number' => $voucher->reference_number,
                'debit'            => $voucher->amount,
            ]);
        }
    }

    //ledger entry
    public function ledgerEntry(
        AccVoucher $voucher,
        string $type,
        string $accType
    ) {
        $accNote = null;
        if (!empty($voucher->note)) {
            $accNote = AccNote::create(['name' => $voucher->note]);
        }

        // which account ID to assign
        $accHeadId = $accType === 'acc_head' ? $voucher->acc_head_id : 0;
        $bankId    = $accType === 'bank'     ? $voucher->acc_bank_id : 0;
        $partyId   = $accType === 'party'    ? $voucher->acc_party_id : 0;

        //debit/credit logic
        $isBankOrParty = in_array($accType, ['bank', 'party']);
        $isRefund      = $voucher->type === 'refund';

        $debit  = $isBankOrParty ? $voucher->amount : 0.00;
        $credit = !$isBankOrParty ? $voucher->amount : 0.00;

        // If it's a refund, swap debit and credit
        if ($isRefund) {
            [$debit, $credit] = [$credit, $debit];
        }

        return AccLedger::create([
            'voucher_id'        => $voucher->id,
            'sale_room_id'      => $voucher->sale_room_id,
            'acc_head_id'       => $accHeadId,
            'bank_id'           => $bankId,
            'party_id'          => $partyId,
            'note_id'           => $accNote?->id??0,
            'date'              => Carbon::now(),
            'type'              => $type,
            'acc_type'          => $accType,
            'is_cash'           => true,
            'reference_number'  => $voucher->reference_number,
            'debit'             => $debit,
            'credit'            => $credit,
        ]);
    }


    // reservation payment ledgers entry
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
        if($voucher->payment_method == 'bank') {
            $this->ledgerEntry(
                $voucher,
                'bank',
                'bank'
            );
        }

        //if payment method is party then insert a party ledger (acc_type:party)
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

    public function reservationRefundLedgersEntry(AccVoucher $voucher) {
        $this->reservationPaymentLedgersEntry($voucher);
    }
}
