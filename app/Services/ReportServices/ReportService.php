<?php

namespace App\Services\ReportServices;

use App\Enums\AccType;
use App\Enums\AccVoucherType;
use App\Enums\LedgerType;
use App\Models\AccHead;
use App\Models\AccLedger;
use App\Models\AccNote;
use App\Models\AccVoucher;
use App\Models\SaleRoom;
use Illuminate\Support\Carbon;
use App\Services\PaymentService;

class ReportService
{
    const SALE_ROOM_HEAD_SLUG           = "sale-room";
    const SALE_ROOM_PAYMENT_HEAD_SLUG   = "sale-room-payment";
    const SALE_ROOM_REFUND_HEAD_SLUG    = "sale-room-refund";
    const SALE_ROOM_ADJUST_HEAD_SLUG    = "sale-room-refund";
    const SALE_ROOM_DISCOUNT_HEAD_SLUG  = "sale-room-discount";
    const SALE_ROOM_CANCELLATION_CHARGE_HEAD_SLUG  = "sale-room-cancellation-charge";
    protected $paymentService;
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }
    public function getHeadIdBySlug(string $slug) {
        return AccHead::select('id')->where('slug', $slug)->first()->id;
    }

    public function getPaymentSummary($saleId)
    {
        $totals = AccLedger::where('type', 'sale')
            ->where('sale_room_id', $saleId)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        return response()->json([
            "total_paid" => (float) $totals->total_credit ?? 0,
            "total_due"  => (float) ($totals->total_debit ?? 0) - $totals->total_credit,
        ]);
    }
    
    public function getledgerData($token){
        
        $data = SaleRoom::with([
            'sale_room_allocation' => function ($query) {
                $query->with(['room_name', 'room']);
            },
            'sale_guest',
            'sale_user',
            'sale_agent',
            'room_categories' => function ($query) {
                $query->with('category');
            }
        ])->where('token', $token)->first();
        if ($data) {
            $id = $data->id;
               $ledgerData =  AccLedger::where('sale_room_id', $id)
                    ->where('type', 'sale')
                    ->with('head')
                    ->get();

            $totals = AccLedger::where('type', 'sale')
                ->where('sale_room_id', $id)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
                $ledger_summary['total_debit']= (float) $totals->total_debit ?? 0;
            $ledger_summary['total_credit'] = (float) $totals->total_credit ?? 0;
            $ledger_summary['total_due'] =  (float) ($totals->total_debit ?? 0) -  (float) ($totals->total_credit ?? 0);
        } else {
            return response()->json(
                [
                    'message' => 'Token Not Found',
                    'error' => $e->getMessage()
                ],
                500
            );
        }
       // dd($ledger_data);
        $categorySummaryCollection = collect($data->room_categories)
            ->where('is_xb', 0)
            ->groupBy('category_id')
            ->map(function ($items, $categoryId) {
                $totalQty = $items->sum('qty');
                $first = $items->first();
                $rateSale = (float) ($first->rate_sale ?? 0);
                $categoryName = $first->category->name ?? 'Unknown';

                $amount = $rateSale * $totalQty;

                return [
                    'category_name' => $categoryName,
                    'rate_sale' => $rateSale,
                    'qty' => $totalQty,
                    'amount' => $amount,
                ];
            });
        $categorySummaryCollectionForExtraBed = collect($data->room_categories)
            ->where('is_xb', 1)
            ->groupBy('category_id')
            ->map(function ($items, $categoryId) {
                $totalQty = $items->sum('qty');
                $first = $items->first();
                $rateSale = (float) ($first->rate_sale ?? 0);
                $categoryName = $first->category->name ?? 'Unknown';

                $amount = $rateSale * $totalQty;

                return [
                    'category_name' => $categoryName,
                    'rate_sale' => $rateSale,
                    'qty' => $totalQty,
                    'amount' => $amount,
                ];
            });
        $categorySummaryStringWithoutXB = $categorySummaryCollection->map(function ($item) {
            return "{$item['category_name']} [" . number_format($item['rate_sale'], 2) . "x{$item['qty']}]";
        })->implode(', ');
        $totalQty = $categorySummaryCollection->sum('qty');

        $totalAmount = $categorySummaryCollection->sum('amount');

        $name = $data->code . ': Ledger';
        $categorySummaryStringForXB = $categorySummaryCollectionForExtraBed->map(function ($item) {
            return "{$item['category_name']} [" . number_format($item['rate_sale'], 2) . "x{$item['qty']}]";
        })->implode(', ');
        $totalXbQty = $categorySummaryCollectionForExtraBed->sum('qty');
        $totalXbAmount = $categorySummaryCollectionForExtraBed->sum('amount');
        $roomTypeRowSpan = 1;
        if (isset($totalXbQty) && $totalXbQty > 0) {
            $roomTypeRowSpan = 2;
        }
        $paymentSummary = $this->getPaymentSummary($id);
        if ($paymentSummary) {
            $total_paid = $paymentSummary->getData()->total_paid;
            if ($total_paid > 0) {
                $total_due = $paymentSummary->getData()->total_due;
            } else {
                $total_due = $totalAmount;
            }
        } else {
            $total_due = $totalAmount;
        }
        $data = [
            'saleRoom' => $data,
            'name' => $name,
            'date' => Carbon::now()->format('d/m/Y'),
            'title' => $name,
            'categorySummaryStringWithoutXB' => $categorySummaryStringWithoutXB,
            'categorySummaryStringForXB' => $categorySummaryStringForXB,
            'totalRoomQty' => $totalQty,
            'totalXbQty' => $totalXbQty,
            'roomTypeRowSpan' => $roomTypeRowSpan,
            'totalAmount' => $totalAmount,
            'totalPaid' => 0.00,
            'totalDue' => $total_due,
            'ledger_data'=> $ledgerData,
            'ledger_summary' => $ledger_summary,
        ];
        return $data;
    }
   
   public function getReservationDetail($token){
        $data = SaleRoom::with([
            'sale_room_allocation' => function ($query) {
                $query->with(['room_name', 'room']);
            },
            'sale_guest',
            'sale_user',
            'sale_agent',
            'room_categories' => function ($query) {
                $query->with('category');
            }
        ])->where('token', $token)->first();
        if ($data) {
            $id = $data->id;
        } else {
            return response()->json(
                [
                    'message' => 'Token Not Found',
                    'error' => $e->getMessage()
                ],
                500
            );
        }
        $categorySummaryCollection = collect($data->room_categories)
            ->where('is_xb', 0)
            ->groupBy('category_id')
            ->map(function ($items, $categoryId) {
                $totalQty = $items->sum('qty');
                $first = $items->first();
                $rateSale = (float) ($first->rate_sale ?? 0);
                $categoryName = $first->category->name ?? 'Unknown';

                $amount = $rateSale * $totalQty;

                return [
                    'category_name' => $categoryName,
                    'rate_sale' => $rateSale,
                    'qty' => $totalQty,
                    'amount' => $amount,
                ];
            });
        $categorySummaryCollectionForExtraBed = collect($data->room_categories)
            ->where('is_xb', 1)
            ->groupBy('category_id')
            ->map(function ($items, $categoryId) {
                $totalQty = $items->sum('qty');
                $first = $items->first();
                $rateSale = (float) ($first->rate_sale ?? 0);
                $categoryName = $first->category->name ?? 'Unknown';

                $amount = $rateSale * $totalQty;

                return [
                    'category_name' => $categoryName,
                    'rate_sale' => $rateSale,
                    'qty' => $totalQty,
                    'amount' => $amount,
                ];
            });
        $categorySummaryStringWithoutXB = $categorySummaryCollection->map(function ($item) {
            return "{$item['category_name']} [" . number_format($item['rate_sale'], 2) . "x{$item['qty']}]";
        })->implode(', ');
        $totalQty = $categorySummaryCollection->sum('qty');

        $totalAmount = $categorySummaryCollection->sum('amount');

       
        $categorySummaryStringForXB = $categorySummaryCollectionForExtraBed->map(function ($item) {
            return "{$item['category_name']} [" . number_format($item['rate_sale'], 2) . "x{$item['qty']}]";
        })->implode(', ');
        $totalXbQty = $categorySummaryCollectionForExtraBed->sum('qty');
        $totalXbAmount = $categorySummaryCollectionForExtraBed->sum('amount');
        $roomTypeRowSpan = 1;
        if (isset($totalXbQty) && $totalXbQty > 0) {
            $roomTypeRowSpan = 2;
        }
        $paymentSummary = $this->getPaymentSummary($id);
        if ($paymentSummary) {
            $total_paid = $paymentSummary->getData()->total_paid;
            if ($total_paid > 0) {
                $total_due = $paymentSummary->getData()->total_due;
            } else {
                $total_due = $totalAmount;
            }
        } else {
            $total_due = $totalAmount;
        }
        $name = $data->code . ': RESERVATION CONFIRMATION SHEET';
        $data = [
            'saleRoom' => $data,
            'name' => $name,
            'date' => Carbon::now()->format('d/m/Y'),
            'title' => $name,
            'categorySummaryStringWithoutXB' => $categorySummaryStringWithoutXB,
            'categorySummaryStringForXB' => $categorySummaryStringForXB,
            'totalRoomQty' => $totalQty,
            'totalXbQty' => $totalXbQty,
            'roomTypeRowSpan' => $roomTypeRowSpan,
            'totalAmount' => $totalAmount,
            'totalPaid' => 0.00,
            'totalDue' => $total_due,
        ];
        return $data;
   }

   public function getCheckinData($params){    
    $checkinData = SaleRoom::with([ 'sale_agent']);
    if(isset($params['date_from'])){
        $checkinData = $checkinData->where('date_checkin','>=',$params['date_from']);
    }
    
    if(isset($params['date_to'])){
        $checkinData = $checkinData->where('date_checkin','<=',$params['date_to']);
    }
   
    if(isset($params['sales_by'])){
        $checkinData = $checkinData->whereIn('ref_id',$params['sales_by']);
    }
    $checkinData = $checkinData->get();
    $data_collection = collect($checkinData);
    $total_rooms = $data_collection->sum('room_qty');
    $total_gross_price = $data_collection->sum('price_gross');
    $total_net_price = $data_collection->sum('price_net');
    $sale_agents = $checkinData->pluck('sale_agent.name')   
    ->unique()                   
    ->implode(', ');
  
    return [
            'from_date' => $params['date_from'] ? \Carbon\Carbon::parse($params['date_from'])->format('d/m/Y') : null,
            'to_date'   => $params['date_to'] ? \Carbon\Carbon::parse($params['date_to'])->format('d/m/Y') : null,
           'checkinData' => $checkinData,
           'total_rooms' => $total_rooms,
           'total_gross_price' => $total_gross_price,
           'total_net_price' => $total_net_price,
           'sale_agents' => $sale_agents,
        ];
    //return $checkinData;
    //dd($checkinData);
   }

   public function getCheckoutData($params){    
    $checkoutData = SaleRoom::with([ 'sale_agent']);
    if(isset($params['date_from'])){
        $checkoutData = $checkoutData->where('date_checkout','>=',$params['date_from']);
    }
    
    if(isset($params['date_to'])){
        $checkoutData = $checkoutData->where('date_checkout','<=',$params['date_to']);
    }
   
    if(isset($params['sales_by'])){
        $checkoutData = $checkoutData->whereIn('ref_id',$params['sales_by']);
    }
    $checkoutData = $checkoutData->get();
    $data_collection = collect($checkoutData);
    $total_rooms = $data_collection->sum('room_qty');
    $total_gross_price = $data_collection->sum('price_gross');
    $total_net_price = $data_collection->sum('price_net');
    $sale_agents = $checkoutData->pluck('sale_agent.name')   
    ->unique()                   
    ->implode(', ');  
    return [
            'from_date' => $params['date_from'] ? \Carbon\Carbon::parse($params['date_from'])->format('d/m/Y') : null,
            'to_date'   => $params['date_to'] ? \Carbon\Carbon::parse($params['date_to'])->format('d/m/Y') : null,
           'checkoutData' => $checkoutData,
           'total_rooms' => $total_rooms,
           'total_gross_price' => $total_gross_price,
           'total_net_price' => $total_net_price,
           'sale_agents' => $sale_agents,
        ];
    //return $checkinData;
    //dd($checkinData);
   }

}
