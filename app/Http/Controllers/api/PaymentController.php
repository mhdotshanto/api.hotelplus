<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AccCashBook;
use App\Models\AccHead;
use App\Models\AccLedger;
use App\Models\AccVoucher;
use App\Models\SaleRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function makeAPayment(Request $request) {

        try {
            DB::beginTransaction();
            $validated = $request->validate([
                'sale_id'           => 'required|numeric|exists:sale_rooms,id',
                'payment_method'    => 'required|in:card,cash,bank,bkash,nagad,rocket',
                'amount'            => 'required|numeric|min:0',
                'reference_number'  => 'required_if:payment_method,card,bkash,nagad,rocket,bank|string|max:255',
                'platform_id'       => 'required_if:payment_method,card,bkash,nagad,rocket,bank|numeric|exists:acc_banks,id',
                'notes'             => 'nullable|string',
                'staff_id'          => 'nullable|numeric|exists:users,id',
            ]);
            $saleRecord = SaleRoom::where('id', $validated['sale_id'])->firstOrFail();

            $hasLedger = AccLedger::where('sale_room_id', $saleRecord->id)->exists();
            if (!$hasLedger) {
                //insert package sale ledger entry
                $this->packageSaleLedgerEntry(
                    $saleRecord->id,
                    $validated['notes']??null,
                    $validated['reference_number'] ?? null,
                    $saleRecord->price_sale
                );

                //insert package sale cash book entry
                $this->insertPackageSaleCashBookEntry(
                    $saleRecord->id,
                    $validated['payment_method'],
                    $validated['reference_number'] ?? null,
                    $saleRecord->price_sale
                );
            }

            //insert reservation payment voucher entry
            $voucher = $this->reservationPaymentVoucherEntry(
                $saleRecord->id,
                $validated['platform_id']??0,
                $validated['notes']??null,
                $validated['reference_number'] ?? null,
                $validated['payment_method'],
                $validated['amount'],
            );

            //insert reservation payment ledger entry
            $this->reservationPaymentLedgerEntry($voucher);

            //insert reservation payment cash book entry
            $this->reservationPaymentCashBookEntry($voucher);
            if($saleRecord->status == 'tentative') {
                $saleRecord->status = 'guaranteed';
                $saleRecord->save();
            }
            DB::commit();
            return response()->json([
                'massage' => 'payment successful',
                'receipt' => $voucher->receipt_ref
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage());
        }
    }

    //insert package sale ledger entry method
    public function packageSaleLedgerEntry (int $saleId, string | null $note, string | null $referenceNumber, string $price) {
        // get acc head
        $saleRoomAccHead = AccHead::where('slug','sale-room')->select('id')->firstOrFail();
        return AccLedger::create([
            'sale_room_id' => $saleId,
            'date' => Carbon::now(),
            'acchead_id' => $saleRoomAccHead->id,
            'head' => 'acc_head',
            'note' => $note,
            'is_sale' => true,
            'reference_number' => $referenceNumber,
            'credit' => $price,
        ]);
    }

    //insert reservation payment voucher entry method
    public function reservationPaymentVoucherEntry(
        int $saleId,
        int $platformId,
        string | null $note,
        string | null $referenceNumber,
        string $paymentMethod,
        string $amount,
    ) {
        $reservationPaymentAccHead = AccHead::where('slug','reservation-payment')->select('id')->firstOrFail();
        return AccVoucher::create([
            'acc_head_id' => $reservationPaymentAccHead->id,
            'acc_bank_id' => $platformId,
            'sale_room_id' => $saleId,
            'note' => $note,
            'reference_number' => $referenceNumber ?? null,
            'payment_method' => $paymentMethod,
            'debit' => $amount,
        ]);
    }

    //insert reservation payment ledger entry method
    public function reservationPaymentLedgerEntry(AccVoucher $voucher) {
        $reservationPaymentLedgerEntry = AccLedger::create([
            'voucher_id' => $voucher->id,
            'sale_room_id' => $voucher->sale_room_id,
            'date' => Carbon::now(),
            'acchead_id' => $voucher->acc_head_id,
            'head' => 'acc_head',
            'note' => $voucher->note,
            'payment_method' => $voucher->payment_method,
            'reference_number' => $voucher->reference_number,
            'debit' => $voucher->debit,
        ]);
        // if bank payment then entry to bank ledger
        if($voucher->payment_method != 'cash') {
            AccLedger::create([
                'voucher_id' => $voucher->id,
                'sale_room_id' => $voucher->sale_room_id,
                'date' => Carbon::now(),
                'acchead_id' => 0,
                'head' => 'acc_bank',
                'note' => $voucher->note,
                'type' => 'bank',
                'payment_method' => $voucher->payment_method,
                'bank_id' => $voucher->acc_bank_id,
                'reference_number' => $voucher->reference_number,
                'debit' => $voucher->debit,
            ]);
        }
        $voucher->status = 'paid';
        $voucher->save();
        return $reservationPaymentLedgerEntry;
    }

    //insert package sale cash book entry method
    public function insertPackageSaleCashBookEntry(
        int $saleId,
        string $paymentMethod,
        string | null $referenceNumber,
        string $amount
    ) {
        // get acc head
        $saleRoomAccHead = AccHead::where('slug','sale-room')->select('id')->firstOrFail();
        AccCashBook::create([
            "sale_room_id" => $saleId,
            "head_id" => $saleRoomAccHead->id,
            "head" => 'acc_head',
            "date" => Carbon::now(),
            "payment_method" => $paymentMethod,
            "reference_number" => $referenceNumber,
            "debit" => $amount,
            "is_sale" => true,
        ]);
        AccCashBook::create([
            "sale_room_id" => $saleId,
            "head_id" => $saleRoomAccHead->id,
            "head" => 'acc_head',
            "date" => Carbon::now(),
            "payment_method" => $paymentMethod,
            "reference_number" => $referenceNumber,
            "credit" => $amount,
            "is_sale" => true,
        ]);
    }

    //insert reservation payment cash book entry method
    public function reservationPaymentCashBookEntry(AccVoucher $voucher) {
        // get acc head
        $reservationPaymentAccHead = AccHead::where('slug','reservation-payment')->select('id')->firstOrFail();
        $debitSideEntry = AccCashBook::create([
            "voucher_id" => $voucher->id,
            "sale_room_id" => $voucher->sale_room_id,
            "head_id" => $reservationPaymentAccHead->id,
            "head" => 'acc_head',
            "date" => Carbon::now(),
            "payment_method" => $voucher->payment_method,
            "reference_number" => $voucher->reference_number,
            "debit" => $voucher->debit,
            "is_cash" => $voucher->payment_method == 'cash',
        ]);
        if (!$debitSideEntry->is_cash) {
            AccCashBook::create([
                "voucher_id" => $voucher->id,
                "sale_room_id" => $voucher->sale_room_id,
                "head_id" => $reservationPaymentAccHead->id,
                "bank_id" => $voucher->acc_bank_id,
                "head" => 'acc_head',
                "date" => Carbon::now(),
                "payment_method" => $voucher->payment_method,
                "reference_number" => $voucher->reference_number,
                "credit" => $voucher->debit,
            ]);
        }
    }
}
