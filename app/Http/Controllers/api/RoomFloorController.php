<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomFloor;

class RoomFloorController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomFloor::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $data = $query->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:10|unique:room_floors,name',
            'is_active' => 'nullable',
        ]);

        RoomFloor::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Room floor created successfully',
        ]);
    }

    public function update(Request $request, $roomFloor)
    {
        \Log::info($request->all());
        $item = RoomFloor::findOrFail($roomFloor);
        
        $validated = $request->validate([
            'name' => 'required|max:10|unique:room_floors,name,' . $item->id,
            'is_active' => 'nullable',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room floor updated successfully',
        ]);
    }

    public function destroy(RoomFloor $roomFloor)
    {
        $roomFloor->update([
            'is_active'=>!$roomFloor->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Room floor updated successfully',
        ]);
    }
}
