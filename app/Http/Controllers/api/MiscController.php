<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AccBank;
use App\Models\AccParty;
use App\Models\Country;
use App\Models\Preference;
use App\Models\RoomCategory;
use App\Models\RoomFloor;
use App\Models\RoomName;
use App\Models\SaleRoom;
use App\Models\User;
use Illuminate\Http\Request;

class MiscController extends Controller
{
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
                $query->where(function ($q) use ($searchTerm) {
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


}
