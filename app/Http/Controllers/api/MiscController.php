<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AccBank;
use App\Models\AccParty;
use App\Models\Config;
use App\Models\Country;
use App\Models\Preference;
use App\Models\RoomCategory;
use App\Models\RoomFloor;
use App\Models\RoomName;
use App\Models\RoomTemp;
use App\Models\SaleRoom;
use App\Models\User;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    public function clearTempRoomSession() {
        try {
            $configs = Config::whereIn('slug', ['temp-session-duration', 'temp-session-modal-duration'])
                ->get()
                ->keyBy('slug');

            $tempSessionDuration = (int) ($configs['temp-session-duration']->value ?? 30);
            $modalSessionDuration = (int) ($configs['temp-session-modal-duration']->value ?? 2);

            $threshold = now()->subMinutes($tempSessionDuration + $modalSessionDuration);
            $exists = RoomTemp::where('created_at', '<', $threshold)->exists();

            if ($exists) {
                RoomTemp::where('created_at', '<', $threshold)
                    ->chunkById(100, function ($records) {
                        foreach ($records as $record) {
                            $record->details()->delete();
                            $record->delete();
                        }
                    });
                return "Old temp sessions cleared.";
            }

            return "No old temp sessions found.";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function preOpenApplicationEvent()
    {
        return response()->json([
            'authenticated' => auth()->check(),
            'clear_session' => $this->clearTempRoomSession()
        ], 200);
    }

    public function getInsertRoomSelectOption() {
        $data = [];
        $data['categories'] = RoomCategory::all();
        $data['room_names'] = RoomName::all();
        $data['floors'] = RoomFloor::all();
        return response()->json($data);
    }

    public function getCategoryOptions() {
        $categories = RoomCategory::all();
        return response()->json($categories);
    }

    public function getRoomNameOptions() {
        $roomNames = RoomName::all();
        return response()->json($roomNames);
    }

    public function getFloorOptions() {
        $floors = RoomFloor::all();
        return response()->json($floors);
    }

    public function getCountryOptions()
    {
        $countries = Country::select('id', 'name', 'nationality')->get();
        return response()->json($countries);
    }

    public function getReferenceOptions()
    {
        $references = User::where('is_ref', 1)->select('id', 'name')->get();
        return response()->json($references);
    }

    public function getPreferenceOptions()
    {
        $preferences = Preference::select('id', 'name')->get();
        return response()->json($preferences);
    }

    public function getPaymentPlatformOptions(Request $request)
    {
        $paymentMethod = $request->get('payment_method');

        if (in_array($paymentMethod, ['card', 'bank'])) {
            $paymentMethod = 'regular';
        }

        return response()->json(
            AccBank::where('banking_type', $paymentMethod)
                ->select('id', 'account_number')
                ->get()
        );
    }

    public function getPartyOptions()
    {
        return response()->json(
            AccParty::select('id', 'name', 'contact_person', 'relation_id')->get()
        );
    }

    public function getGlobalSearchOptions(Request $request)
    {
        $searchTerm = $request->get('searchTerm');

        $options = SaleRoom::query()
            ->when($searchTerm, function ($query, $searchTerm) {
                $query->whereIn('status', ['tentative', 'guaranteed', 'checkedin', 'postponed'])
                ->where(function ($q) use ($searchTerm) {
                    $q->where('code', 'like', "%{$searchTerm}%")
                        ->orWhere('guest_name_first', 'like', "%{$searchTerm}%")
                        ->orWhere('guest_name_last', 'like', "%{$searchTerm}%")
                        ->orWhere('guest_mobile', 'like', "%{$searchTerm}%")
                        ->orWhere('guest_mobile_emergency', 'like', "%{$searchTerm}%")
                        ->orWhere('guest_email', 'like', "%{$searchTerm}%")
                        ->orWhere('guest_company', 'like', "%{$searchTerm}%")
                        ->orWhere('date_checkin', 'like', "%{$searchTerm}%")
                        ->orWhere('date_checkout', 'like', "%{$searchTerm}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id',
                'code',
                'guest_name_title',
                'guest_name_first',
                'guest_name_last',
                'guest_mobile',
                'guest_email',
                'guest_company',
                'date_checkin',
                'date_checkout',
                'status',
                'rooms',
                'master_room'
            ]);
        return response()->json($options);
    }

    public function getStatusCounts(Request $request)
    {
        $statuses = [
            'all',
            'tentative',
            'guaranteed',
            'checkedin',
            'to_checkin',
            'checkin_missed',
            'checkedout',
            'to_checkout',
            'checkout_missed',
            'cancelled',
            'postponed'
        ];

//        all: number;
//        tentative: number;
//        guaranteed: number;
//        checkedin: number;
//        to_checkin: number;
//        checkin_missed: number;
//        checkedout: number;
//        to_checkout: number;
//        checkout_missed: number;
//        postponed: number;
//        cancelled: number;

        $counts = [];
        $all_count = 0;
        foreach ($statuses as $status) {
           if($status == 'all') continue;
           if($status == 'to_checkin'){
                $counts[$status] = SaleRoom::onArrivalGuests()->count()??0;
           } else if ($status == 'checkin_missed') {
                $counts[$status] = SaleRoom::checkinMissedGuests()->count() ?? 0;
            }
           else if($status == 'to_checkout'){
                $counts[$status] = SaleRoom::onDepartureGuests()->count()??0;
           } else if($status == 'checkout_missed'){
               $counts[$status] = SaleRoom::checkoutMissedGuests()->count()??0;
           } else {
               $counts[$status] = SaleRoom::where('status', $status)->count()??0;
           }
            $all_count += $counts[$status];
        }
        $counts['all'] = $all_count;
        return response()->json($counts);
    }
}
