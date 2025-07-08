<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomName;

class RoomNameController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomName::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)->orWhere('short_name', 'LIKE', $searchTerm);
            });
        }

        $roomNames = $query->orderBy('name', 'ASC')->paginate($request->get('per_page'));
        return response()->json($roomNames);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:40',
            'short_name' => 'required|string|max:16',
            'category_id' => 'required|integer|exists:room_categories,id',
            'bed' => 'nullable|string|in:SGL,DBL,TWN,TPL,4 BED',
            'rate' => 'required|numeric|min:0',
            'rate_xb' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);
        RoomName::create($validatedData);
        return response()->json([
            'success' => true,
            'message' => 'Room name created successfully',
        ]);
    }

    public function update(Request $request, $room_name)
    {
        $item = RoomName::findOrFail($room_name);

        $validated = $request->validate([
            'name' => 'required|max:50|unique:room_names,name,' . $item->id,
            'short_name' => 'required|max:8|unique:room_names,short_name,'. $item->id,
            'is_active' => 'nullable',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room name updated successfully',
        ]);
    }

    public function destroy(RoomName $roomName)
    {
        $roomName->update([
            'is_active'=>!$roomName->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Room name updated successfully',
        ]);
    }
}
