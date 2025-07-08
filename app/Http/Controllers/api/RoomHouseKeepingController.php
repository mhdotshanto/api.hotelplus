<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomNumber;
use App\Models\RoomHouseKeeping;

class RoomHouseKeepingController extends Controller
{
    public function index()
    {
        $data['room_house_keepings'] = RoomHouseKeeping::with('room')->get();
        $data['rooms'] = RoomNumber::all();
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'room_number_id' => 'required|exists:room_numbers,id',
            'note' => 'required|max:255',
        ]);

        RoomHouseKeeping::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Room house keeping created successfully',
        ]);
    }

    public function update(Request $request, $roomHouseKeeping)
    {
        $item = RoomHouseKeeping::findOrFail($roomHouseKeeping);
        
        $validated = $request->validate([
            'room_number_id' => 'required|exists:room_numbers,id',
            'note' => 'required|max:255',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room house keeping updated successfully',
        ]);
    }

    public function destroy(RoomHouseKeeping $roomHouseKeeping)
    {
        $roomHouseKeeping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room house keeping deleted successfully',
        ]);
    }
}
