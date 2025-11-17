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
            'payment_details.discountType' => 'nullable|string|in:no-discount,by-invoice,by-category,customize-rate',

            // by-invoice data validation
            'payment_details.byInvoiceDiscount' => 'nullable|array',
            'payment_details.byInvoiceDiscount.percentage' => 'nullable|numeric|min:0|max:100',
            'payment_details.byInvoiceDiscount.amount' => 'nullable|numeric|min:0|max:999999.99',

            // by-category data validation
            'payment_details.byCategoryDiscount ' => 'nullable|array',
            'payment_details.byCategoryDiscount.*.room.amount' => 'nullable|numeric|min:0',
            'payment_details.byCategoryDiscount.*.room.percentage' => 'nullable|numeric|min:0|max:100',
            'payment_details.byCategoryDiscount.*.xb.amount' => 'nullable|numeric|min:0',
            'payment_details.byCategoryDiscount.*.xb.percentage' => 'nullable|numeric|min:0|max:100',

            //customize-rate data validation
            'payment_details.customRates' => 'required|array',
            'payment_details.customRates.*.roomRate' => 'nullable|numeric|min:0|max:999999.99',
            'payment_details.customRates.*.room_qty' => 'nullable|numeric|min:0|max:999999.99',
            'payment_details.customRates.*.xbRate' => 'nullable|numeric|min:0',
            'payment_details.customRates.*.xb_qty' => 'nullable|numeric|min:0',

            'payment_details.masterRoom' => 'nullable|string|max:50',
            'payment_details.saleType' => 'nullable|string|in:FIT,GROUP',
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

    public function createSaleRoomAllocations ($tempDetails, $saleId)
    {
        foreach ($tempDetails as $room) {
            SaleRoomAllocation::create([
                'room_sale_id' => $saleId,
                'room_number_id' => $room->room_number_id,
                'room_category_id' => $room->room_category_id,
                'room_name_id' => $room->room_name_id,
                'bed' => $room->bed,
                'date' => $room->date,
                'is_xb' => $room->has_xb,
            ]);
        }
    }

    public function createSaleCategories(
        $groupedData,
        $byCategoryDiscount,
        $discountType,
        $discountPer,
        $saleId,
        $tempDetailsCollection
    ){
        $insertable = [];
        foreach ($groupedData as $key => $item) {
            $item = $item[0];
            [$category, $date, $bed] = explode(' | ', $key);
            $currentCategory = $byCategoryDiscount[$category." ".$bed];
            $qty = $tempDetailsCollection->where('category.name', $category)->where('bed', $bed)->where('date', $date)->sum('qty');
            $discountPerItem = 0.0;
            $discountAmountItem = 0.0;
            $rateSale = 0;

            $grossRate = $this->applyVatAndServiceCharge($item['roomName']['rate'])->gross;

            if ($discountType === 'by-invoice') {
                $discountPerItem = $discountPer;
                $discountAmountItem = $grossRate * ($discountPerItem / 100);
                $rateSale = $grossRate - $discountAmountItem;
            } elseif ($discountType === 'by-category') {
                $discountPerItem = $currentCategory['room']['percentage'];
                $discountAmountItem = $grossRate * ($discountPerItem / 100);
                $rateSale = $grossRate - $discountAmountItem;
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
                'rate'              => $item['roomName']['rate'],
                'gross_rate'        => $grossRate,
                'discount_per'      => $this->fixDecimal($discountPerItem),
                'discount_amount'   => $this->fixDecimal($discountAmountItem),
                'rate_sale'         => $this->fixDecimal($rateSale),
            ];

            if ($item['has_xb']) {
                $grossXbRate = $this->applyVatAndServiceCharge($item['roomName']['rate_xb'])->gross;
                if ($discountType === 'by-category') {
                    $discountPerItem = $currentCategory['xb']['percentage'];
                    $discountAmountItem = $grossXbRate * ($discountPerItem / 100);
                    $rateSale = $grossXbRate - $discountAmountItem;
                }
                $insertable[] = [
                    'sale_id'           => $saleId,
                    'date'              => $date,
                    'category_id'       => $item['room_category_id'],
                    'bed'               => $item['roomName']['bed'],
                    'qty'               => $qty,
                    'is_xb'             => 1,
                    'rate'              => $item['roomName']['rate_xb'],
                    'gross_rate'        => $grossXbRate,
                    'discount_per'      => $this->fixDecimal($discountPerItem),
                    'discount_amount'   => $this->fixDecimal($discountAmountItem),
                    'rate_sale'         => $this->fixDecimal($rateSale),
                ];
            }
        }
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
        return collect($customRates)->sum(function ($rate) {
            return ($rate['roomRate'] * $rate['room_qty']) + ($rate['xbRate'] * $rate['xb_qty']);
        });
    }


}
