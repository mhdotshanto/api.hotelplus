<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomService;

class RoomServiceController extends Controller
{
    public function index()
    {
        $data = RoomService::all();
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:100|unique:room_services,name',
        ]);

        RoomService::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Room service created successfully',
        ]);
    }

    public function update(Request $request, $roomService)
    {
        $item = RoomService::findOrFail($roomService);
        
        $validated = $request->validate([
            'name' => 'required|max:100|unique:room_services,name,' . $item->id,
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room service updated successfully',
        ]);
    }

    public function destroy(RoomService $roomService)
    {
        $roomService->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room service deleted successfully',
        ]);
    }
}
