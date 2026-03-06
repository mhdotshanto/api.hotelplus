<?php
namespace App\Traits;

use App\Models\Config;
use App\Models\SaleRoom;
use App\Models\SaleRoomAllocation;
use App\Models\SaleRoomCategory;
use Carbon\Carbon;

trait SaleRoomTrait
{
    function fixDecimal( $number, $decimal_places = 2, $round_type = PHP_ROUND_HALF_UP) {
        return round($number, $decimal_places, $round_type);
    }

    public function saleRoomRules()
    {
        return [
            'guest_id' => 'nullable|integer|min:1',
            'temp_id' => 'nullable|integer|min:1',
            'status' => 'nullable|in:tentative,checkedin',

            'payment_details' => 'nullable|array',

            'payment_details.manualNetPrice' => 'nullable|numeric|min:0|max:999999.99',
            'payment_details.pendingNetPrice' => 'nullable|numeric|min:0|max:999999.99',
            'payment_details.discountType' => 'nullable|in:no-discount,by-invoice,by-category,customize-rate',

            'payment_details.byInvoiceDiscount' => 'nullable|array',
            'payment_details.byInvoiceDiscount.percentage' => 'nullable|numeric|min:0|max:100',
            'payment_details.byInvoiceDiscount.amount' => 'nullable|numeric|min:0|max:999999.99',

            'payment_details.byCategoryDiscount' => 'nullable|array',
            'payment_details.byCategoryDiscount.*.*.room.amount' => 'nullable|numeric|min:0',
            'payment_details.byCategoryDiscount.*.*.room.percentage' => 'nullable|numeric|min:0|max:100',
            'payment_details.byCategoryDiscount.*.*.xb.amount' => 'nullable|numeric|min:0',
            'payment_details.byCategoryDiscount.*.*.xb.percentage' => 'nullable|numeric|min:0|max:100',

            'payment_details.customRates' => 'required|array',
            'payment_details.customRates.*.*.roomRate' => 'required|numeric|min:0',
            'payment_details.customRates.*.*.room_qty' => 'required|integer|min:0',
            'payment_details.customRates.*.*.xbRate' => 'required|numeric|min:0',
            'payment_details.customRates.*.*.xb_qty' => 'required|integer|min:0',

            'payment_details.masterRoom' => 'nullable|string|max:50',
            'payment_details.saleType' => 'nullable|in:FIT,GROUP',
            'payment_details.paxGuest' => 'nullable|integer|min:0|max:100',
            'payment_details.eta' => 'nullable|string',
            'payment_details.etd' => 'nullable|string',
            'payment_details.remarks' => 'nullable|string|max:400',
        ];
    }

    public function createSaleRoom(
        $validated,
        $guest,
        $authenticatedUser,
        $temp,
        $grossPrice,
        $totalPrice,
        $priceSale,
        $discountType,
        $discountPer,
        $discountAmount,
        $roomNames,
        $config
    ){
        $discount_type = 'none';

        if ($discountType === 'by-invoice') $discount_type = 'invoice';
        if ($discountType === 'by-category') $discount_type = 'category';
        if ($discountType === 'customize-rate') $discount_type = 'custom_rate';

        return SaleRoom::create([
            'guest_id' => $guest->id,
            'guest_name_title' => $guest->name_address,
            'guest_name_first' => $guest->first_name,
            'guest_name_last' => $guest->last_name,
            'guest_mobile' => $guest->mobile,
            'guest_mobile_emergency' => $guest->emergency_contact,
            'guest_email' => $guest->email,
            'guest_company' => $guest->company,
            'guest_address' => $guest->perm_address,
            'ref_id' => $guest->ref_id,
            'user_id' => $authenticatedUser->id,
            'master_room' => $validated['payment_details']['masterRoom'],
            'rooms' => implode(', ', $roomNames),
            'guest_qty' => $validated['payment_details']['paxGuest'],
            'room_qty' => $temp->details->sum('qty'),
            'xb_qty' => $temp->details->where('has_xb', 1)->sum('qty'),
            'date' => now(),
            'price' => $totalPrice,
            'vat_per' => $config['vat'],
            'vat_amount' => $this->fixDecimal($grossPrice->vat_amount),
            'servicecharge_per' => $config['service-charge'],
            'servicecharge_amount' => $this->fixDecimal($grossPrice->service_charge_amount),
            'price_gross' => $grossPrice->gross,
            'discount_type' => $discount_type,
            'discount_per' => $discountPer,
            'discount_amount' => $this->fixDecimal($discountAmount),
            'price_sale' => $priceSale,
            'price_net' => $priceSale,
            'remarks' => $validated['payment_details']['remarks'],
            'date_checkin' => $temp->details->min('date'),
            'date_checkout' => Carbon::parse($temp->details->max('date'))->addDay()->format('Y-m-d'),
            'eta' => Carbon::parse($validated['payment_details']['eta'])->format('H:i:s'),
            'etd' => Carbon::createFromFormat('h:i A', $validated['payment_details']['etd'])->format('H:i:s'),
            'date_to_confirm' => now(),
            'status' => $validated['status'],
            'is_room_allocated' => 0,
        ]);
    }

    public function applyVatAndServiceCharge($amount)
    {
        $config = Config::select('slug', 'value')->whereIn('slug', ['vat', 'service-charge'])->get()->pluck('value', 'slug')->toArray();
        $vat_amount = $amount * ($config['vat'] / 100);
        $service_charge = $amount * ($config['service-charge'] / 100);
        return (object)[
            'vat_amount' => $vat_amount,
            'service_charge_amount' => $service_charge,
            'gross' => $amount + $vat_amount + $service_charge
        ];
    }

    public function createSaleRoomAllocations (
        $tempDetails,
        $saleId,
    ){
        foreach ($tempDetails as $room) {
            $roomKey = "{$room->date}|{$room->room_category_id}|{$room->bed}|0";
            $roomRateSale = $this->getCurrentSaleRateFromSaleRoomCategory($saleId, $roomKey);

            SaleRoomAllocation::create([
                'room_sale_id'          => $saleId,
                'room_number_id'        => $room->room_number_id,
                'room_category_id'      => $room->room_category_id,
                'room_name_id'          => $room->room_name_id,
                'bed'                   => $room->bed,
                'date'                  => $room->date,
                'is_xb'                 => 0,
                'rate_sale'             => $this->fixDecimal($roomRateSale),
                'tip_amount'            => 0.00, //later updated when tip per updated on sale room parent record
                'rate_net'              => $this->fixDecimal($roomRateSale),
            ]);

            if ($room->has_xb) {
                $xbKey = "{$room->date}|{$room->room_category_id}|{$room->bed}|1";

                $xbRateSale = $this->getCurrentSaleRateFromSaleRoomCategory(
                    $saleId,
                    $xbKey
                );

                SaleRoomAllocation::create([
                    'room_sale_id'          => $saleId,
                    'room_number_id'        => $room->room_number_id,
                    'room_category_id'      => $room->room_category_id,
                    'room_name_id'          => $room->room_name_id,
                    'bed'                   => $room->bed,
                    'date'                  => $room->date,
                    'is_xb'                 => 1,
                    'rate_sale'             => $this->fixDecimal($xbRateSale),
                    'tip_amount'            => 0.00, //later updated when tip per updated on sale room parent record
                    'rate_net'              => $this->fixDecimal($xbRateSale),
                ]);
            }
        }
    }

    public function createSaleCategories(
        $groupedData,
        $byCategoryDiscount,
        $discountType,
        $discountPer,
        $saleId,
        $tempDetailsCollection,
        $customRates = []
    ){
        $isCustomRate = $discountType === 'customize-rate';
        $insertable = [];
        foreach ($groupedData as $key => $group) {
            $item = $group[0];
            [$categoryName, $date, $bed] = explode(' | ', $key);

            $categoryBedKey = $categoryName." ".$bed;
            $rateKey = round($item['roomName']['rate']) . '_' . round($item['roomName']['rate_xb']);

            $currentCategory = $byCategoryDiscount[$categoryBedKey];

            $qty = $tempDetailsCollection
                    ->where('category.name', $categoryName)
                    ->where('bed', $bed)
                    ->where('date', $date)
                    ->sum('qty');

            $xbQty = $tempDetailsCollection
                ->where('category.name', $categoryName)
                ->where('bed', $bed)
                ->where('date', $date)
                ->where('has_xb', 1)
                ->sum('qty');

            $roomRate = $isCustomRate ? $customRates[$categoryBedKey][$rateKey]['roomRate'] : $item['rate'];
            $xbRate   = $isCustomRate ? $customRates[$categoryBedKey][$rateKey]['xbRate']   : $item['rate_xb'];

            $grossRate = $this->applyVatAndServiceCharge($roomRate)->gross;

            if ($discountType === 'by-invoice') {
                $discountPerItem = $discountPer;
                $discountAmountItem = $grossRate * ($discountPerItem / 100);
                $rateSale = $grossRate - $discountAmountItem;
            } elseif ($discountType === 'by-category') {
                $discountPerItem = $currentCategory[$rateKey]['room']['percentage'];
                $discountAmountItem = $grossRate * ($discountPerItem / 100);
                $rateSale = $grossRate - $discountAmountItem;
            } elseif ($discountType === 'customize-rate')   {
                $rateSale = $grossRate;
                $discountAmountItem = 0.00;
                $discountPerItem = 0.00;
            } else {
                $rateSale = $grossRate;
                $discountAmountItem = 0.00;
                $discountPerItem = 0.00;
            }

            $insertable[] = [
                'sale_id'           => $saleId,
                'date'              => $date,
                'category_id'       => $item['room_category_id'],
                'bed'               => $item['roomName']['bed'],
                'qty'               => $qty,
                'is_xb'             => 0,
                'rate'              => $roomRate,
                'gross_rate'        => $grossRate,
                'discount_per'      => $this->fixDecimal($discountPerItem),
                'discount_amount'   => $this->fixDecimal($discountAmountItem),
                'rate_sale'         => $this->fixDecimal($rateSale),
                'rate_net'         => $this->fixDecimal($rateSale),
            ];

            if ($xbQty > 0) {
                $grossXbRate = $this->applyVatAndServiceCharge($xbRate)->gross;
                if ($discountType === 'by-invoice') {
                    $discountPerItem = $discountPer;
                    $discountAmountItem = $grossXbRate * ($discountPerItem / 100);
                    $rateSale = $grossXbRate - $discountAmountItem;
                } elseif ($discountType === 'by-category') {
                    $discountPerItem = $currentCategory[$rateKey]['room']['percentage'];
                    $discountAmountItem = $grossXbRate * ($discountPerItem / 100);
                    $rateSale = $grossXbRate - $discountAmountItem;
                } elseif ($discountType === 'customize-rate')   {
                    $rateSale = $grossXbRate;
                    $discountAmountItem = 0.00;
                    $discountPerItem = 0.00;
                } else {
                    $rateSale = $grossXbRate;
                    $discountAmountItem = 0.00;
                    $discountPerItem = 0.00;
                }
                $insertable[] = [
                    'sale_id'           => $saleId,
                    'date'              => $date,
                    'category_id'       => $item['room_category_id'],
                    'bed'               => $item['roomName']['bed'],
                    'qty'               => $xbQty,
                    'is_xb'             => 1,
                    'rate'              => $xbRate,
                    'gross_rate'        => $grossXbRate,
                    'discount_per'      => $this->fixDecimal($discountPerItem),
                    'discount_amount'   => $this->fixDecimal($discountAmountItem),
                    'rate_sale'         => $this->fixDecimal($rateSale),
                    'rate_net'         => $this->fixDecimal($rateSale),
                ];
            }
        }
//        return $insertable;
        SaleRoomCategory::insert($insertable);
    }

    private function calculateDiscount(
        $discountType,
        $grossPrice,
        $byInvoiceDiscount = [],
        $byCategoryDiscount = []
    ) {
        $discountAmount = 0.0;
        $discountPer = 0.0;

        if ($discountType === 'by-invoice') {
            $discountAmount = (float) ($byInvoiceDiscount['amount'] ?? 0);
            $discountPer = $grossPrice->gross > 0
                ? $this->fixDecimal(($discountAmount / $grossPrice->gross) * 100)
                : 0.0;

        } elseif ($discountType === 'by-category') {
            $discountAmount = collect($byCategoryDiscount)->flatMap(fn($item) => [
                $item['room']['amount'] ?? 0,
                $item['xb']['amount'] ?? 0,
            ])->sum();

            $discountPer = $grossPrice->gross > 0
                ? $this->fixDecimal(($discountAmount / $grossPrice->gross) * 100)
                : 0.0;
        }

        return [
            'discount_amount' => $discountAmount,
            'discount_per' => $discountPer,
        ];
    }

    public function getTempDetailsTotalPrice($tempDetails) {
        return collect($tempDetails)->sum(function ($detail) {
            $roomRate = (float) $detail->roomName->rate;
            $roomTotal = $detail->qty * $roomRate;
            $xbTotal = $detail->has_xb ? 1 * (float) $detail->roomName->rate_xb : 0;
            return $roomTotal + $xbTotal;
        });
    }

    public function customRateTotal($customRates)
    {
        return collect($customRates)
            ->flatMap(fn ($categoryRates) => $categoryRates)
            ->sum(function ($rate) {
                return ($rate['roomRate'] * $rate['room_qty']) + ($rate['xbRate'] * $rate['xb_qty']);
            });
    }

    public function getCurrentSaleRateFromSaleRoomCategory(
        int $saleId,
        string $key
    ) {
        static $rateSaleCache = [];

        if (!isset($rateSaleCache[$saleId])) {
            $rateSaleCache[$saleId] = SaleRoomCategory::where('sale_id', $saleId)
                ->get()
                ->mapWithKeys(function ($cat) {
                    $mapKey = "{$cat->date}|{$cat->category_id}|{$cat->bed}|{$cat->is_xb}";
                    return [$mapKey => (float) $cat->rate_sale];
                })
                ->toArray();
        }

        if (!isset($rateSaleCache[$saleId][$key])) {
            throw new \RuntimeException(
                "SaleRoomCategory rate_sale not found for sale_id={$saleId}, key={$key}"
            );
        }

        return $rateSaleCache[$saleId][$key];
    }
}
