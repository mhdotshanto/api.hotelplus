<?php

namespace App\Services;

use App\Enums\AccType;
use App\Enums\AccVoucherType;
use App\Enums\LedgerType;
use App\Models\AccHead;
use App\Models\AccLedger;
use App\Models\AccNote;
use App\Models\AccVoucher;
use App\Models\SaleRoom;
use Illuminate\Support\Carbon;

class PaymentService
{
    const SALE_ROOM_HEAD_SLUG           = "sale-room";
    const SALE_ROOM_PAYMENT_HEAD_SLUG   = "sale-room-payment";
    const SALE_ROOM_REFUND_HEAD_SLUG    = "sale-room-refund";
    const SALE_ROOM_ADJUST_HEAD_SLUG    = "sale-room-refund";
    const SALE_ROOM_DISCOUNT_HEAD_SLUG  = "sale-room-discount";
    const SALE_ROOM_CANCELLATION_CHARGE_HEAD_SLUG  = "sale-room-cancellation-charge";
    const SALE_ROOM_TIP_HEAD_SLUG  = "sale-room-tip";

    public function getHeadIdBySlug(string $slug) {
        return AccHead::select('id')->where('slug', $slug)->first()->id;
    }

    //create voucher entry
    public function createVoucher(
        AccVoucherType $voucherType,
        int $saleId,
        string $amount,
        int $bankId = 0,
        int $partyId = 0,
        int $receiverId = 0,
        ?string $note = null,
        ?string $referenceNumber = null,
        string $paymentMethod = 'N/A',
    ) : AccVoucher {
        $accHeadId = 0;
        $status = 'pending';
        $receiptRef = 'N/A';

        if ($voucherType === AccVoucherType::BILL) {
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_HEAD_SLUG);
            $status = 'N/A';
        }

        if ($voucherType === AccVoucherType::PAYMENT){
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_PAYMENT_HEAD_SLUG);
            $receiptRef = AccVoucher::generateReceiptRef();
        }

        if ($voucherType === AccVoucherType::ADJUSTMENT) {
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_ADJUST_HEAD_SLUG);
            $status = 'paid';
        }

        if ($voucherType === AccVoucherType::DISCOUNT) {
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_DISCOUNT_HEAD_SLUG);
            $status = 'paid';
        }

        if ($voucherType === AccVoucherType::REFUND) {
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_REFUND_HEAD_SLUG);
            $status = 'paid';
        }

        if ($voucherType === AccVoucherType::CANCEL_RESERVATION) {
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_CANCELLATION_CHARGE_HEAD_SLUG);
            $status = 'paid';
        }

        if ($voucherType === AccVoucherType::TIP) {
            $accHeadId = $this->getHeadIdBySlug(self::SALE_ROOM_TIP_HEAD_SLUG);
            $status = 'paid';
        }

        return AccVoucher::create([
            "receipt_ref"      => $receiptRef,
            "acc_head_id"      => $accHeadId,
            "acc_bank_id"      => $bankId,
            "sale_room_id"     => $saleId,
            "acc_party_id"     => $partyId,
            "receiver_id"      => $receiverId,
            "type"             => $voucherType->value,
            "note"             => $note,
            "reference_number" => $referenceNumber,
            "payment_method"   => $paymentMethod,
            "status"           => $status,
            "amount"           => $amount,
        ]);
    }

    public function insertCashbookEntry(
        AccType $accType,
        AccVoucher $voucher,
        string $entryType,
        bool $isCash = false,
        int|null $customAccHeadId = null,
    ): AccLedger {
        if (!in_array($entryType, ['debit', 'credit'], true)) throw new \InvalidArgumentException("Invalid entry type: {$entryType}");

        // which account ID to assign
        $accHeadId = $accType === AccType::ACC_HEAD ? ($customAccHeadId ?? $voucher->acc_head_id) : 0;
        $bankId    = $accType === AccType::BANK     ? $voucher->acc_bank_id : 0;
        $partyId   = $accType === AccType::PARTY    ? $voucher->acc_party_id : 0;

        return AccLedger::create([
            "voucher_id"        => $voucher->id,
            "sale_room_id"      => $voucher->sale_room_id,
            "acc_head_id"       => $accHeadId,
            "bank_id"           => $bankId,
            "party_id"          => $partyId,
            "date"              => Carbon::now(),
            "type"              => 'cashbook',
            "acc_type"          => $accType->value,
            "is_cash"           => $isCash,
            "reference_number"  => $voucher->reference_number,
            "debit"             => $entryType === 'debit' ? $voucher->amount : 0.00,
            "credit"            => $entryType === 'credit' ? $voucher->amount : 0.00,
        ]);
    }

    //ledger entry
    public function ledgerEntry(
        LedgerType $ledgerType,
        AccType $accType,
        AccVoucher $voucher,
        string $entryType = "debit",
        int|null $customAccHeadId = null,
    ): AccLedger {
        if (!in_array($entryType, ['debit', 'credit'], true)) throw new \InvalidArgumentException("Invalid entry type: {$entryType}");

        $accNoteId = 0;
        if (!empty($voucher->note)) $accNoteId = AccNote::firstOrCreate(['name' => $voucher->note])->id;

        // which account ID to assign
        $accHeadId = $accType === AccType::ACC_HEAD ? ( $customAccHeadId ?? $voucher->acc_head_id) : 0;
        $bankId    = $accType === AccType::BANK     ? $voucher->acc_bank_id : 0;
        $partyId   = $accType === AccType::PARTY    ? $voucher->acc_party_id : 0;

        return AccLedger::create([
            "voucher_id"        => $voucher->id,
            "sale_room_id"      => $voucher->sale_room_id,
            "acc_head_id"       => $accHeadId,
            "bank_id"           => $bankId,
            "party_id"          => $partyId,
            "note_id"           => $accNoteId,
            "date"              => Carbon::now(),
            "type"              => $ledgerType->value,
            "acc_type"          => $accType->value,
            "is_cash"           => true,
            "reference_number"  => $voucher->reference_number,
            "debit"             => $entryType === 'debit' ? $voucher->amount : 0.00,
            "credit"            => $entryType === 'credit' ? $voucher->amount : 0.00,
        ]);
    }

    public function confirmReservationAndAssociatedLedgerEntries(SaleRoom $saleRoom)
    {
        //reservation billing voucher entry
        $billingVoucher = $this->createVoucher(
            voucherType: AccVoucherType::BILL,
            saleId: $saleRoom->id,
            amount: $saleRoom->price_sale,
            note: "Room Reservation Bill"
        );

        //sale cashbook debit side entry (acc_type:acc_head)
        $this->insertCashbookEntry(
            accType: AccType::ACC_HEAD,
            voucher: $billingVoucher,
            entryType: "debit"
        );

        //sale cashbook credit side entry (acc_type:sale)
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $billingVoucher,
            entryType: 'credit'
        );

        //sale ledger entry (acc_type:sale)
        $this->ledgerEntry(
            ledgerType: LedgerType::SALE,
            accType: AccType::SALE,
            voucher: $billingVoucher,
            entryType: "debit"
        );

        //general ledger entry (acc_type:acc_head)
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $billingVoucher,
            entryType: "debit"
        );

        $saleRoom->is_confirmed = true;
        $saleRoom->save();
    }

    //insert reservation payment cashbook entry
    public function reservationPaymentCashBookEntry(AccVoucher $voucher){
        // cashbook entry for (acc_type:sale) debit side
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'debit',
            isCash: $voucher->payment_method === 'cash',
        );

        // if bank payment then cashbook entry (acc_type:bank) credit side
        if ($voucher->payment_method == 'bank') {
            $this->insertCashbookEntry(
                accType: AccType::BANK,
                voucher: $voucher,
                entryType: 'credit',
            );
        }

        // if party then cashbook entry (acc_type:party) credit side
        if ($voucher->payment_method == 'party') {
            $this->insertCashbookEntry(
                accType: AccType::PARTY,
                voucher: $voucher,
                entryType: 'credit',
            );
        }
    }

    //insert reservation refund cashbook entry
    public function reservationRefundCashbookEntry(AccVoucher $voucher) {
        // cashbook entry for refund amount (acc_type:sale) credit side
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'credit',
            isCash: $voucher->payment_method === 'cash',
        );

        // if bank payment then cashbook entry for refund amount (acc_type:bank) debit side
        if ($voucher->payment_method == 'bank') {
            $this->insertCashbookEntry(
                accType: AccType::BANK,
                voucher: $voucher,
                entryType: 'debit',
            );
        }

        // if party then cashbook entry  for refund amount (acc_type:party) debit side
        if ($voucher->payment_method == 'party') {
            $this->insertCashbookEntry(
                accType: AccType::PARTY,
                voucher: $voucher,
                entryType: 'debit',
            );
        }
    }

    // reservation payment ledgers entry
    public function reservationPaymentLedgersEntry(AccVoucher $voucher) {
        //sale ledger entry (acc_type:sale)
        $this->ledgerEntry(
            ledgerType: LedgerType::SALE,
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'credit'
        );

        //general ledger entry (acc_type:acc_head)
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'credit'
        );

        //if payment method is bank then insert a bank ledger (acc_type:bank)
        if($voucher->payment_method == 'bank') {
            $this->ledgerEntry(
                ledgerType: LedgerType::BANK,
                accType: AccType::BANK,
                voucher: $voucher,
            );
        }

        //if payment method is party then insert a party ledger (acc_type:party)
        if($voucher->payment_method == 'party') {
            $this->ledgerEntry(
                ledgerType: LedgerType::PARTY,
                accType: AccType::PARTY,
                voucher: $voucher,
            );
        }
        $voucher->status = 'paid';
        $voucher->save();
    }

    public function reservationRefundLedgersEntry(AccVoucher $voucher) {

        //sale ledger entry (acc_type:sale)
        $this->ledgerEntry(
            ledgerType: LedgerType::SALE,
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'debit'
        );

        //general ledger entry (acc_type:acc_head)
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'debit'
        );

        //if payment method is bank then insert a bank ledger (acc_type:bank)
        if($voucher->payment_method == 'bank') {
            $this->ledgerEntry(
                ledgerType: LedgerType::BANK,
                accType: AccType::BANK,
                voucher: $voucher,
                entryType: 'credit'
            );
        }

        //if payment method is party then insert a party ledger (acc_type:party)
        if($voucher->payment_method == 'party') {
            $this->ledgerEntry(
                ledgerType: LedgerType::PARTY,
                accType: AccType::PARTY,
                voucher: $voucher,
                entryType: 'credit'
            );
        }

    }

    //adjust or discount cashbook entries
    public function insertAdjustOrDiscountCashbookEntries(AccVoucher $voucher){
        // cashbook entry (acc_type:acc_head)
        $this->insertCashbookEntry(
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'credit'
        );

        // cashbook entry (acc_type:sale)
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'debit'
        );

    }

    //adjust or discount ledger entries
    public function insertAdjustOrDiscountLedgerEntries(AccVoucher $voucher) {
        //sale ledge entry credit (acc_head:sale)
        $this->ledgerEntry(
            ledgerType: LedgerType::SALE,
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'credit'
        );

        //general ledge entry credit (acc_head:acc_head)
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'credit'
        );
    }

    //cancel reservation cashbook ledger entries
    public function cancelReservationCashbookEntries(AccVoucher $voucher) {

        //credit side entry(accType:ACC_HEAD)(ACC_HEAD:Room Sale)
        $this->insertCashbookEntry(
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'credit',
            customAccHeadId: $this->getHeadIdBySlug(self::SALE_ROOM_HEAD_SLUG)
        );

        //debit side entry (accType:ACC_HEAD)(ACC_HEAD:Room Sale Cancellation Charge)
        $this->insertCashbookEntry(
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'debit',
        );

        //debit side entry(accType:Sale)(ACC_HEAD:Room Sale)
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'debit',
        );

        //debit side entry(accType:Sale)(ACC_HEAD:Room Sale)
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'credit',
        );

    }

    //cancel reservation ledger entries
    public function cancelReservationLedgerEntries(AccVoucher $voucher)
    {
        //sale ledger entry (acc_type:sale) credit side
        // milon bhai------------------------
        // $this->ledgerEntry(
        //     ledgerType: LedgerType::SALE,
        //     accType: AccType::SALE,
        //     voucher: $voucher,
        //     entryType: "credit"
        // );
        // milon bhai------------------------

        //sale ledger entry (acc_type:sale) debit side
        $this->ledgerEntry(
            ledgerType: LedgerType::SALE,
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: "debit"
        );


        //general ledger entry (acc_type:ACC_HEAD) credit side
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: "credit",
            customAccHeadId:  $this->getHeadIdBySlug(self::SALE_ROOM_HEAD_SLUG)
        );

        //general ledger entry (acc_type:ACC_HEAD) debit side
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: "debit",
        );
    }

    public function getPaymentSummary($saleId)
    {
        $totals = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $saleId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalRefunds = (float) (AccVoucher::where('sale_room_id', $saleId)
            ->where('type', 'refund')
            ->sum('amount') ?? 0);

        $totalCredit = (float) ($totals->total_credit ?? 0);
        $totalDebit  = (float) ($totals->total_debit ?? 0);

        return response()->json([
            "total_paid"   => $totalCredit - $totalRefunds,
            "total_refund" => $totalRefunds,
            "total_due"    => $totalDebit - $totalCredit,
        ]);
    }

    public function adjustInitialLedges(
        int $saleId,
        string $amountStatus,
        string $amount
    ) {
        if (!in_array($amountStatus, ['increased', 'decreased', 'no_change'], true)) throw new \InvalidArgumentException("Invalid entry type: {$amountStatus}");

        $billingVoucher = $this->createVoucher(
            voucherType: AccVoucherType::BILL,
            saleId: $saleId,
            amount: $amount,
            note: $amountStatus == "increased" ? "Reservation Bill Increased" : "Reservation Bill Decreased"
        );

        $this->insertCashbookEntry(
            accType: AccType::ACC_HEAD,
            voucher: $billingVoucher,
            entryType: $amountStatus == "increased" ? "debit" : "credit"
        );

        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $billingVoucher,
            entryType: $amountStatus == "increased" ? "credit" : "debit"
        );

        $this->ledgerEntry(
            ledgerType: LedgerType::SALE,
            accType: AccType::SALE,
            voucher: $billingVoucher,
            entryType: $amountStatus == "increased" ? "debit" : "credit"
        );

        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $billingVoucher,
            entryType: $amountStatus == "increased" ? "debit" : "credit"
        );
    }

    //insert reservation tip ledger entry
    public function reservationTipLedgersEntry(AccVoucher $voucher)
    {
        //sale ledger entry (acc_type:sale)
//        $this->ledgerEntry(
//            ledgerType: LedgerType::SALE,
//            accType: AccType::SALE,
//            voucher: $voucher,
//        );

        //general ledger entry (acc_type:acc_head)
        $this->ledgerEntry(
            ledgerType: LedgerType::GENERAL,
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
        );

        $isCash = true;

        //if payment method is bank then insert a bank ledger (acc_type:bank)
        if ($voucher->payment_method == 'bank') {
            $this->ledgerEntry(
                ledgerType: LedgerType::BANK,
                accType: AccType::BANK,
                voucher: $voucher,
                entryType: 'credit'
            );
            $isCash = false;
        }

        //if payment method is party then insert a party ledger (acc_type:party)
        if ($voucher->payment_method == 'party') {
            $this->ledgerEntry(
                ledgerType: LedgerType::PARTY,
                accType: AccType::PARTY,
                voucher: $voucher,
                entryType: 'credit'
            );
            $isCash = false;
        }

        if (!$isCash) {
            $this->insertCashbookEntry(
                accType: AccType::ACC_HEAD,
                voucher: $voucher,
                entryType: 'debit',
                isCash: false
            );
        }

        $this->insertCashbookEntry(
            accType: AccType::ACC_HEAD,
            voucher: $voucher,
            entryType: 'credit',
            isCash: $isCash
        );
    }

    //insert reservation tip cashbook entry
    public function reservationTipCashbookEntry(AccVoucher $voucher)
    {
        // cashbook entry for tip amount (acc_type:sale) credit side
        $this->insertCashbookEntry(
            accType: AccType::SALE,
            voucher: $voucher,
            entryType: 'credit',
            isCash: $voucher->payment_method === 'cash',
        );

        // if bank payment then cashbook entry for tip amount (acc_type:bank) debit side
        if ($voucher->payment_method == 'bank') {
            $this->insertCashbookEntry(
                accType: AccType::BANK,
                voucher: $voucher,
                entryType: 'debit',
            );
        }

        // if party then cashbook entry  for refund amount (acc_type:party) debit side
        if ($voucher->payment_method == 'party') {
            $this->insertCashbookEntry(
                accType: AccType::PARTY,
                voucher: $voucher,
                entryType: 'debit',
            );
        }
    }

    public function adjustLedgersForSaleTip(AccVoucher $voucher){
        $this->reservationTipLedgersEntry($voucher);
        $this->reservationTipCashbookEntry($voucher);
    }

    /**
     * Insert a direct sale ledger adjustment entry without a voucher.
     */
    public function adjustSaleLedgerEntry(
        int $saleId,
        float|string $amount,
        string $entryType = 'credit',
        ?string $note = null,
        ?string $headSlug = self::SALE_ROOM_HEAD_SLUG,
        ?string $referenceNumber = null,
    ): AccLedger {
        if (!in_array($entryType, ['debit', 'credit'], true)) throw new \InvalidArgumentException("Invalid entry type: {$entryType}");

        $accHeadId = $this->getHeadIdBySlug($headSlug ?? self::SALE_ROOM_HEAD_SLUG);

        $accNoteId = 0;
        if (!empty($note)) {
            $accNote = AccNote::firstOrCreate(['name' => $note]);
            $accNoteId = $accNote->id;
        }

        return AccLedger::create([
            'voucher_id'        => 0,
            'sale_room_id'      => $saleId,
            'acc_head_id'       => $accHeadId,
            'bank_id'           => 0,
            'party_id'          => 0,
            'note_id'           => $accNoteId,
            'date'              => Carbon::now(),
            'type'              => LedgerType::SALE->value,
            'acc_type'          => AccType::SALE->value,
            'is_cash'           => true,
            'reference_number'  => $referenceNumber,
            'debit'             => $entryType === 'debit' ? (float) $amount : 0.00,
            'credit'            => $entryType === 'credit' ? (float) $amount : 0.00,
        ]);
    }
}
