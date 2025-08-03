<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AccLedger;
use App\Models\SaleRoom;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function makeAPayment(Request $request, PaymentService $paymentService) {

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

            //confirm reservation and associated ledger entries
            if(!$saleRecord->is_confirmed) $paymentService->confirmReservationAndAssociatedLedgerEntries($saleRecord);

            //insert reservation payment voucher entry
            $voucher = $paymentService->reservationPaymentVoucherEntry(
                $saleRecord->id,
                $validated['platform_id']??0,
                $validated['notes']??null,
                $validated['reference_number'] ?? null,
                $validated['payment_method'],
                $validated['amount'],
            );

            //insert reservation payment ledger entry
            $paymentService->reservationPaymentLedgersEntry($voucher);

            //insert reservation payment cash book entry
            $paymentService->reservationPaymentCashBookEntry($voucher);

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
}
