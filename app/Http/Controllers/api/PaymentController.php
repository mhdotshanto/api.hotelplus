<?php

namespace App\Http\Controllers\api;

use App\Enums\AccVoucherType;
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
                'payment_method'    => 'required|in:cash,card,bank,bkash,nagad,rocket,party',
                'amount'            => 'required|numeric|min:0',
                'reference_number'  => 'required_if:payment_method,card,bkash,nagad,rocket,bank|string|max:255',
                'platform_id'       => 'required_if:payment_method,card,bkash,nagad,rocket,bank|numeric|exists:acc_banks,id',
                'party_id'          => 'required_if:payment_method,party|exists:acc_parties,id',
                'notes'             => 'nullable|string',
                'staff_id'          => 'nullable|numeric|exists:users,id',
            ]);

            if (in_array($validated['payment_method'], ['card', 'bank', 'bkash', 'nagad', 'rocket'])) {
                $validated['payment_method'] = 'bank';
            }

            $saleRecord = SaleRoom::where('id', $validated['sale_id'])->firstOrFail();

            //confirm reservation and associated ledger entries
            if(!$saleRecord->is_confirmed) $paymentService->confirmReservationAndAssociatedLedgerEntries($saleRecord);

            //insert reservation payment voucher entry
            $voucher = $paymentService->createVoucher(
                voucherType: AccVoucherType::PAYMENT,
                saleId: $saleRecord->id,
                amount: $validated['amount'],
                bankId: $validated['platform_id']??0,
                partyId: $validated['party_id']??0,
                receiverId: $validated['staff_id']??0,
                note: $validated['notes']??null,
                referenceNumber: $validated['reference_number'] ?? null,
                paymentMethod: $validated['payment_method'],
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

    public function refund(Request $request, PaymentService $paymentService) {
        try {
            DB::beginTransaction();
            $validated = $request->validate([
                'sale_id'           => 'required|numeric|exists:sale_rooms,id',
                'payment_method'    => 'required|in:cash,card,bank,bkash,nagad,rocket,party',
                'amount'            => 'required|numeric|min:0',
                'reference_number'  => 'required_if:payment_method,card,bkash,nagad,rocket,bank|string|max:255',
                'platform_id'       => 'required_if:payment_method,card,bkash,nagad,rocket,bank|numeric|exists:acc_banks,id',
                'party_id'          => 'required_if:payment_method,party|exists:acc_parties,id',
                'notes'             => 'nullable|string',
                'staff_id'          => 'nullable|numeric|exists:users,id',
            ]);

            if (in_array($validated['payment_method'], ['card', 'bank', 'bkash', 'nagad', 'rocket'])) $validated['payment_method'] = 'bank';
            $saleRecord = SaleRoom::where('id', $validated['sale_id'])->firstOrFail();

            $voucher = $paymentService->createVoucher(
                voucherType: AccVoucherType::REFUND,
                saleId: $saleRecord->id,
                amount: $validated['amount'],
                bankId: $validated['platform_id']??0,
                partyId: $validated['party_id']??0,
                receiverId: $validated['staff_id']??0,
                note: $validated['notes']??null,
                referenceNumber: $validated['reference_number']??null,
                paymentMethod: $validated['payment_method']
            );

            //insert reservation refund ledger entry
            $paymentService->reservationRefundLedgersEntry($voucher);
            //insert reservation refund cash book entry
            $paymentService->reservationRefundCashbookEntry($voucher);

            DB::commit();
            return response()->json([
                'massage' => 'refund successful',
                'receipt' => $voucher->receipt_ref
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage());
        }
    }

    public function adjustOrDiscountSale(
        Request $request,
        SaleRoom $saleRoom,
        PaymentService $paymentService
    ) {
        try {
            DB::beginTransaction();
            $validatedData = $request->validate([
                'amount'   => 'required|numeric',
                'note'     => 'nullable|string|max:500',
                'action'   => 'required|in:adjustment,discount',
                'staff_id' => 'numeric|exists:users,id',
            ]);

            $voucher = $paymentService->createVoucher(
                voucherType: $validatedData['action'] == 'adjustment' ? AccVoucherType::ADJUSTMENT : AccVoucherType::DISCOUNT,
                saleId: $saleRoom->id,
                amount: $validatedData['amount'],
                receiverId: $validatedData['staff_id'],
                note: $validatedData['note'],
            );

            $paymentService->insertAdjustOrDiscountCashbookEntries($voucher);
            $paymentService->insertAdjustOrDiscountLedgerEntries($voucher);

            $saleRoom->discount_note    = $voucher->note;
            $saleRoom->adjusted_amount  = $voucher->amount;
            $saleRoom->save();

            DB::commit();
            return response()->json([
                'message' => 'processed successfully.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fullSaleCancellation(
        Request $request,
        SaleRoom $saleRoom,
        PaymentService $paymentService
    ) {
        try {
            DB::beginTransaction();
            $validatedData = $request->validate([
                'cancellation_charge'   => 'required|numeric',
            ]);

            //cancellation voucher
            $voucher = $paymentService->createVoucher(
                voucherType: AccVoucherType::CANCEL_RESERVATION,
                saleId: $saleRoom->id,
                amount: $validatedData['cancellation_charge'],
            );

            //insert cancel reservation cashbook entries
            $paymentService->cancelReservationCashbookEntries($voucher);
            //insert cancel reservation ledger entries
            $paymentService->cancelReservationLedgerEntries($voucher);

            $saleRoom->status = "cancelled";
            $saleRoom->save();

            DB::commit();
            return response()->json([
                'message' => 'processed successfully.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function refundAndCancelReservation(
        Request $request,
        SaleRoom $saleRoom,
        PaymentService $paymentService
    ) {
        try {
            DB::beginTransaction();
            $validated = $request->validate([
                'cancellation_charge'   => 'required|numeric|min:0',
                'payment_method'        => 'required|in:cash,card,bank,bkash,nagad,rocket,party',
                'amount'                => 'required|numeric|min:0',
                'reference_number'      => 'required_if:payment_method,card,bkash,nagad,rocket,bank|string|max:255',
                'platform_id'           => 'required_if:payment_method,card,bkash,nagad,rocket,bank|numeric|exists:acc_banks,id',
                'party_id'              => 'required_if:payment_method,party|exists:acc_parties,id',
                'notes'                 => 'nullable|string',
                'staff_id'              => 'nullable|numeric|exists:users,id',
            ]);

            if (in_array($validated['payment_method'], ['card', 'bank', 'bkash', 'nagad', 'rocket'])) $validated['payment_method'] = 'bank';

//            return response()->json($validated);

            if ($validated['amount'] > 0) {
                $RefundVoucher = $paymentService->createVoucher(
                    voucherType: AccVoucherType::REFUND,
                    saleId: $saleRoom->id,
                    amount: $validated['amount'],
                    bankId: $validated['platform_id']??0,
                    partyId: $validated['party_id']??0,
                    receiverId: $validated['staff_id']??0,
                    note: $validated['notes']??null,
                    referenceNumber: $validated['reference_number']??null,
                    paymentMethod: $validated['payment_method']
                );

                //insert reservation refund ledger entry
                $paymentService->reservationRefundLedgersEntry($RefundVoucher);
                //insert reservation refund cash book entry
                $paymentService->reservationRefundCashbookEntry($RefundVoucher);
            }

            if ($validated['cancellation_charge'] > 0) {
                //cancellation voucher
                $cancellationVoucher = $paymentService->createVoucher(
                    voucherType: AccVoucherType::CANCEL_RESERVATION,
                    saleId: $saleRoom->id,
                    amount: $validated['cancellation_charge'],
                );

                //insert cancel reservation cashbook entries
                $paymentService->cancelReservationCashbookEntries($cancellationVoucher);
                //insert cancel reservation ledger entries
                $paymentService->cancelReservationLedgerEntries($cancellationVoucher);

            }

            $saleRoom->status = "cancelled";
            $saleRoom->save();

            DB::commit();
            return response()->json([
                'message' => 'processed successfully.',
            ], 200);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage());
        }
    }

    public function getPaymentSummary($saleId) {
            $totals = AccLedger::where('type', 'sale')
                ->where('sale_room_id', $saleId)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            return response()->json([
                "total_paid" => (float) $totals->total_credit ?? 0,
                "total_due"  => (float) ($totals->total_debit ?? 0) - $totals->total_credit,
            ]);
    }

}
