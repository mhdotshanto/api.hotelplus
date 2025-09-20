<?php

namespace App\Services\ReportServices;

use App\Enums\AccType;
use App\Enums\AccVoucherType;
use App\Enums\LedgerType;
use App\Models\AccHead;
use App\Models\AccLedger;
use App\Models\AccNote;
use App\Models\AccVoucher;
use App\Models\RoomNumber;
use App\Models\SaleRoom;
use App\Models\SaleRoomAllocation;
use Illuminate\Support\Carbon;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

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
       // dd($paymentSummary);
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
            ->groupBy(function ($item) {
                return $item->category_id . '-' . $item->bed;
            })
            ->map(function ($items, $key) {
                $totalQty = $items->sum('qty');
                $first = $items->first();
                $rateSale = (float) ($first->rate_sale ?? 0);
                $categoryName = $first->category->name ?? 'Unknown';

                $amount = $rateSale * $totalQty;
                return [
                    'category_name' => $categoryName,
                    'bed' => $first->bed,
                    'rate_sale' => $rateSale,
                    'qty' => $totalQty,
                    'amount' => $amount,
                ];
            });

        $categorySummaryCollectionForExtraBed = collect($data->room_categories)
            ->where('is_xb', 1)
            ->groupBy(function ($item) {
                return $item->category_id . '-' . $item->bed;
            })
            ->map(function ($items, $categoryId) {
                $totalQty = $items->sum('qty');
                $first = $items->first();
                $rateSale = (float) ($first->rate_sale ?? 0);
                $categoryName = $first->category->name ?? 'Unknown';

                $amount = $rateSale * $totalQty;

                return [
                    'category_name' => $categoryName,
                    'bed' => $first->bed,
                    'rate_sale' => $rateSale,
                    'qty' => $totalQty,
                    'amount' => $amount,
                ];
            });
        $categorySummaryStringWithoutXB = $categorySummaryCollection->map(function ($item) {
            return "{$item['category_name']}-{$item['bed']} [" . number_format($item['rate_sale'], 2) . "x{$item['qty']}]";
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
            'totalPaid' => $total_paid??0.00,
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
   
    if(isset($params['references'])){
        $checkinData = $checkinData->whereIn('ref_id',$params['references']);
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
   
    if(isset($params['references'])){
        $checkoutData = $checkoutData->whereIn('ref_id',$params['references']);
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
    public function getDailyRoomData($params)
    {
        $date = $params['date'] ?? null;
        $roomType = $params['roomType'];

        $rooms = RoomNumber::with([
            'roomName.category',
            'room_sale_allocation.saleRoom.sale_user:id,name,user_name',
            'room_sale_allocation.saleRoom.sale_agent:id,name,user_name',
        ]);

        if ($roomType === "not_sold") {
            $rooms = $rooms->whereDoesntHave('room_sale_allocation');
        }

        $rooms = $rooms->get();
        $queryData = $rooms->flatMap(function ($room) use ($date, $roomType) {
            //dd($date,$roomType);
            if($roomType=="sold" || $roomType=="all"){
                
            }
            $allocations = $room->room_sale_allocation
                ->filter(fn($alloc) => $date ? $alloc->saleRoom->date === $date : true);

            // If allocations found for the date → map them
            if ($allocations->isNotEmpty() ) {
                return $allocations->map(function ($alloc) use ($room, $roomType) {
                    $saleRoom = $alloc->saleRoom;
                    if($roomType == "sold" || $roomType == "all"){
                        
                    }
                    return [
                        'room_name'          => $room->name ?? "",
                        'room_category_name' => $room->roomName->category->name ?? "",
                        'code'               => $saleRoom->code ?? "",
                        'status'             => $saleRoom->status ?? "",
                        'guest_name'         => $saleRoom->guest_name_title
                            ? ($saleRoom->guest_name_title . " " . $saleRoom->guest_name_first . " " . $saleRoom->guest_name_last)
                            : "",
                        'guest_address'      => $saleRoom->guest_address ?? "",
                        'guest_mobile'       => $saleRoom->guest_mobile ?? "",
                        'date_checkin'       => $saleRoom->date_checkin ?? "",
                        'date_checkout'      => $saleRoom->date_checkout ?? null,
                        'period'             => Carbon::parse($saleRoom->date_checkin)->format('d/m/Y') . " to " . Carbon::parse($saleRoom->date_checkout)->format('d/m/Y') ?? "" ,
                        'created_by_name'    => optional($saleRoom->sale_user)->user_name
                            ? ($saleRoom->sale_user->user_name . ' [' . $saleRoom->sale_user->name . ']')
                            : "",
                        'reference_name'     => optional($saleRoom->sale_agent)->user_name
                            ? ($saleRoom->sale_agent->user_name . ' [' . $saleRoom->sale_agent->name . ']')
                            : "",
                        // 👇 force cast to float so sum() won’t break
                        'room_rent'          => (float) ($saleRoom->price_sale ?? 0),
                        'commision'          => (float) ($saleRoom->commision_amount ?? 0),
                        'price_net'          => (float) ($saleRoom->price_net ?? 0),
                    ];
                });
            }

            // No allocation → return default row
            if ($roomType == "not_sold" || $roomType == "all") {
                return [[
                    'room_name'          => $room->name ?? "",
                    'room_category_name' => $room->roomName->category->name ?? "",
                    'code'               => "",
                    'status'             => "",
                    'guest_name'         => "",
                    'guest_address'      => "",
                    'guest_mobile'       => "",
                    'date_checkin'       => "",
                    'date_checkout'      => "",
                    'period'             => "",
                    'created_by_name'    => "",
                    'reference_name'     => "",
                    'room_rent'          => (float) 0,
                    'commision'          => (float) 0,
                    'price_net'          => (float) 0,
                ]];
            }
        });
        $totalSale      = $queryData->sum('room_rent');
        $totalCommission = $queryData->sum('commision');
        $totalNetSale   = $queryData->sum('price_net');
        $totalIncome    = $totalNetSale;
        return [
            'date'        => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : null,
            'roomData'    => $queryData->values(), // reset indexes            
            'summary' => [
                'total_not_sold_room'      => $queryData->count(),
                'total_sold_room'      => $queryData->whereIn('status', ['guaranteed', 'checkedin', 'checkedout', 'tentative'])->count(),
                'total_sale'      => number_format($totalSale, 2),
                'total_commision' => number_format($totalCommission, 2),
                'total_net_sale'  => number_format($totalNetSale, 2),
                'total_income'    => number_format($totalIncome, 2),
            ],
        ];
    }

     public function getInhouseGuestData($params)
    {
        //dd($params);
        $today = now()->toDateString();
        //dd($today);
        $reportType = $params['reportType'];
        $roomSaleData = SaleRoom::select(
        'sale_rooms.*',
        DB::raw('COUNT(DISTINCT sale_room_allocations.id) as total_rooms'),
        DB::raw('GROUP_CONCAT(DISTINCT sale_room_allocations.room_number_id SEPARATOR ",") as room_numbers')
        )
        ->leftJoin('sale_room_allocations', 'sale_rooms.id', '=', 'sale_room_allocations.room_sale_id')    
        ->groupBy('sale_rooms.id');      
       // dd($roomSaleData->toSql());
       if(isset($reportType)){
            if($reportType=='inhouseGuest'){
            $roomSaleData = $roomSaleData->inhouseGuests();
            //dd($roomSaleData->toSql(), $roomSaleData->get());
            } elseif($reportType=='onArrival'){
                $roomSaleData = $roomSaleData->onArrivalGuests();
            } elseif($reportType=='onDeparture'){
                $roomSaleData = $roomSaleData->onDepartureGuests();
            } elseif($reportType=='checkinMissed'){
                $roomSaleData = $roomSaleData->checkinMissedGuests();
            }elseif($reportType=='checkoutMissed'){
                $roomSaleData = $roomSaleData->checkoutMissedGuests();
            }
       }
        
       // dd($roomSaleData->get());
        
        $roomSaleData = $roomSaleData->get();
        $total_rooms = $roomSaleData->sum('room_qty');
        $total_gross_price = $roomSaleData->sum('price_gross');
        $total_discount = $roomSaleData->sum('discount_amount');
        $total_net_price = $roomSaleData->sum('price_net');
    
        return [
            'roomSaleData'  => $roomSaleData,            
            'total_rooms' => $total_rooms,
            'total_gross_price' => $total_gross_price,
            'total_discount' => $total_discount??0.00,
            'total_net_price' => $total_net_price,
            'total_collection' => $total_net_price,
            'total_refund' => 0.00,
            'total_balance' => $total_net_price,
            ];
    }

    public function getArrivalGuestData($params)
    {        
        $roomSaleData = SaleRoom::select(
            'sale_rooms.*',
            DB::raw('COUNT(DISTINCT sale_room_allocations.id) as total_rooms'),
            DB::raw('GROUP_CONCAT(DISTINCT sale_room_allocations.room_number_id SEPARATOR ",") as room_numbers')
        )
            ->leftJoin('sale_room_allocations', 'sale_rooms.id', '=', 'sale_room_allocations.room_sale_id')
            ->groupBy('sale_rooms.id');
            $roomSaleData = $roomSaleData->onArrivalGuests();

        $roomSaleData = $roomSaleData->get();
        $total_rooms = $roomSaleData->sum('room_qty');
        $total_gross_price = $roomSaleData->sum('price_gross');
        $total_discount = $roomSaleData->sum('discount_amount');
        $total_net_price = $roomSaleData->sum('price_net');

        return [
            'roomSaleData'  => $roomSaleData,
            'total_rooms' => $total_rooms,
            'total_gross_price' => $total_gross_price,
            'total_discount' => $total_discount ?? 0.00,
            'total_net_price' => $total_net_price,
            'total_collection' => $total_net_price,
            'total_refund' => 0.00,
            'total_balance' => $total_net_price,
        ];
    }
    public function getGuestByDate($params)
    {   
        //dd($params);     
        $roomSaleData = SaleRoom::select(
            'sale_rooms.*',
            DB::raw('COUNT(DISTINCT sale_room_allocations.id) as total_rooms'),
            DB::raw('GROUP_CONCAT(DISTINCT sale_room_allocations.room_number_id SEPARATOR ",") as room_numbers')
        )
            ->leftJoin('sale_room_allocations', 'sale_rooms.id', '=', 'sale_room_allocations.room_sale_id')
            ->groupBy('sale_rooms.id');
            if(isset($params['reportType']) && $params['reportType']=='checkedin'){
                $roomSaleData = $roomSaleData->where('date_checkin','>=',$params['date_from']);
                $roomSaleData = $roomSaleData->where('date_checkin','<=',$params['date_to']);
            } elseif(isset($params['reportType']) && $params['reportType']=='checkedout'){
                $roomSaleData = $roomSaleData->where('date_checkout','>=',$params['date_from']);
                $roomSaleData = $roomSaleData->where('date_checkout','<=',$params['date_to']);
            }            

        $roomSaleData = $roomSaleData->get();
        $total_rooms = $roomSaleData->sum('room_qty');       
        $total_net_price = $roomSaleData->sum('price_net');

        return [
            'roomSaleData'  => $roomSaleData,
            'total_rooms' => $total_rooms,            
            'total_net_price' => $total_net_price,
            'total_balance' => $total_net_price,
            'status' => $params['reportType']=='checkedin' ? "CHECKIN" : "CHECKOUT",
        ];
    }
    public function getAllGuestData($params)
    {        
        $roomSaleData = SaleRoom::select(
            'sale_rooms.*',
            DB::raw('COUNT(DISTINCT sale_room_allocations.id) as total_rooms'),
            DB::raw('GROUP_CONCAT(DISTINCT sale_room_allocations.room_number_id SEPARATOR ",") as room_numbers')
        )
            ->leftJoin('sale_room_allocations', 'sale_rooms.id', '=', 'sale_room_allocations.room_sale_id')
            ->groupBy('sale_rooms.id');           
        $roomSaleData = $roomSaleData->get();
        $total_rooms = $roomSaleData->sum('room_qty');
        $total_gross_price = $roomSaleData->sum('price_gross');
        $total_discount = $roomSaleData->sum('discount_amount');
        $total_net_price = $roomSaleData->sum('price_net');

        return [
            'roomSaleData'  => $roomSaleData,
            'total_rooms' => $total_rooms,
            'total_gross_price' => $total_gross_price,
            'total_discount' => $total_discount ?? 0.00,
            'total_net_price' => $total_net_price,
            'total_collection' => $total_net_price,
            'total_refund' => 0.00,
            'total_balance' => $total_net_price,
        ];
    }
    
    public function getMoneyReceiptData($saleId){
       $data =  AccVoucher::with('saleRoom', 'receiver')->where('id', $saleId)
            ->where('payment_method', '!=', 'N/A')
            ->orderBy('created_at', 'ASC')
            ->first();
            //dd($data);
        return $data;
    }
}
