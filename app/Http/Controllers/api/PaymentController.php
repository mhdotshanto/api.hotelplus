<?php

namespace App\Http\Controllers\api;

use App\Enums\AccVoucherType;
use App\Http\Controllers\Controller;
use App\Models\AccLedger;
use App\Models\AccVoucher;
use App\Models\SaleRoom;
use App\Models\SaleRoomCategory;
use App\Models\SaleTip;
use App\Services\PaymentService;
use App\Services\ReportServices\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\SaleRoomTrait;
use App\Traits\Formatter;
class PaymentController extends Controller
{
    use SaleRoomTrait, Formatter;
    private $reportService;
    public function __construct(ReportService $reportService){
        $this->reportService = $reportService;
    }

    public function makeAPayment(Request $request, PaymentService $paymentService) {
//dd($request->all());
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
                'received_by'       => 'nullable|numeric|exists:users,id',
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
                receiverId: $validated['received_by']?auth()->id():0,
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

     public function tip(Request $request, PaymentService $paymentService) {
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
                'name'              => 'required|string',
                'contact_no'        => 'required|string',
            ]);

            if (in_array($validated['payment_method'], ['card', 'bank', 'bkash', 'nagad', 'rocket'])) $validated['payment_method'] = 'bank';
            $saleRecord = SaleRoom::where('id', $validated['sale_id'])->firstOrFail();

            SaleTip::create([
                "sale_room_id"  => $saleRecord->id,
                "name"          => $validated['name'],
                "contact_no"    => $validated['contact_no'],
                "note"          => $validated['notes'],
                "amount"        => $validated['amount']
            ]);

            $voucher = $paymentService->createVoucher(
                voucherType: AccVoucherType::TIP,
                saleId: $saleRecord->id,
                amount: $validated['amount'],
                bankId: $validated['platform_id']??0,
                partyId: $validated['party_id']??0,
                receiverId: $validated['staff_id']??0,
                note: $validated['notes']??null,
                referenceNumber: $validated['reference_number']??null,
                paymentMethod: $validated['payment_method']
            );

            $paymentService->adjustLedgersForSaleTip($voucher);

            DB::commit();
            return response()->json([
                'massage' => 'Tip transaction successful',
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
            $saleCategoryRecords = SaleRoomCategory::where('sale_id', $saleRoom->id)->get();
            $totalCategoryRateSale = $saleCategoryRecords->sum('rate_sale');
            $totalAdjustedAmount = $validatedData['amount'];
            //dd('total tip..',$totalTip);
            $totalAdjustedPer = $this->fixDecimal(($totalAdjustedAmount * 100) / $totalCategoryRateSale);
            $updatedCategoryWithAdjustmentCalculation = collect($saleCategoryRecords)->map(function ($item) use ($totalAdjustedPer) {
                $item['adjustment_per'] = $this->fixDecimal($totalAdjustedPer);
                $item['adjustment_amount'] = $this->fixDecimal(($item->rate_sale * $totalAdjustedPer) / 100);
                $item['rate_net'] = $this->fixDecimal($item['rate_sale'] - ($item['adjustment_amount'] + $item['tip_amount']));
                $item->save();
                return $item;
            });
//dd($updatedCategoryWithAdjustmentCalculation);
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
                'cancellation_charge'   => 'required|numeric|min:0',
                'note'                  => 'nullable|string|max:200',
            ]);

//            return response()->json($validatedData);

            if (!is_null($validatedData['note']) && !$saleRoom->note) {$saleRoom->note()->create(['note' => $validatedData['note'],]);}

            if ($validatedData['cancellation_charge'] > 0) {
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
            }

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
    public function getPaymentSummary($saleId){
        return $this->reportService->getPaymentSummary($saleId);
    }
    public function getPaymentSummary_old($saleId) {
        $total_bills = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $saleId)
            ->whereIn('voucher_id', function ($q) use ($saleId) {
                $q->select('id')
                    ->from('acc_vouchers')
                    ->where('sale_room_id', $saleId)
                    ->where('type', 'bill');
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit,SUM(debit)-SUM(credit) as total_bill_amount')
            ->first();
        $total_payments = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $saleId)
            ->whereIn('voucher_id', function ($q) use ($saleId) {
                $q->select('id')
                    ->from('acc_vouchers')
                    ->where('sale_room_id', $saleId)
                    ->whereIn('type',['refund','payment']);
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit,SUM(credit)-SUM(debit) as total_payment_amount')
            ->first();
            $totalTips = AccVoucher::where('sale_room_id',$saleId)
            ->where('type','tip')->selectRaw('SUM(amount) as total_tip_amount')->first();
        $total_adjustment = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $saleId)
            ->whereIn('voucher_id', function ($q) use ($saleId) {
                $q->select('id')
                    ->from('acc_vouchers')
                    ->where('sale_room_id', $saleId)
                    ->where('type', 'adjustment');
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit,SUM(credit)-SUM(debit) as total_adjustment')
            ->first();

            // $typeWiseSummary = AccVoucher::where('sale_room_id', $saleId)
            //     ->where('type', '!=', 'bill')->selectRaw('type,sum(amount) as total')->groupBy('type')->pluck('total','type');
            //     $total_payment = $typeWiseSummary->get('payment',0);
            //     $total_refund = $typeWiseSummary->get('refund',0);
            //     $total_adjustment = $typeWiseSummary->get('adjustment',0);
            return response()->json([
                "total_bill" => (float) $total_bills->total_bill_amount,
                "total_paid" => (float) $total_payments->total_payment_amount ?? 0,
                "total_adjustment" => (float) $total_adjustment->total_adjustment,
                "total_tip" => (float) $totalTips->total_tip_amount,
                "total_due"  => (float) ($total_bills->total_bill_amount??0) - (($total_payments->total_payment_amount ?? 0) +($total_adjustment->total_adjustment??0) + ($totalTips->total_tip_amount ?? 0)),

                // "total_payment" => $total_payment,
                // "total_refund"=> $total_refund,
                // "total_adjustment"=> $total_adjustment
            ]);
    }

}
