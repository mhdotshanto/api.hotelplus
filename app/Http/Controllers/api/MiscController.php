<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Preference;
use App\Models\RoomCategory;
use App\Models\RoomFloor;
use App\Models\RoomName;
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
}
