<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomCategory;

class RoomCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomCategory::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('rate', 'LIKE', '%' . $request->search . '%');
            });
        }

        $categories = $query->orderBy('id', 'ASC')
            ->paginate($request->get('per_page'));

        return response()->json($categories);
    }

    public function getAll(Request $request)
    {
        $data = RoomCategory::all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:40|unique:room_categories,name',
//            'rate' => 'nullable|numeric',
//            'rate_xb' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

//        return response()->json($validatedData);

        RoomCategory::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Room Category created successfully',
        ]);
    }

    public function update(Request $request, $roomCategory)
    {
        $item = RoomCategory::findOrFail($roomCategory);

        $validated = $request->validate([
            'name' => 'required|max:40|unique:room_categories,name,' . $item->id,
//            'rate' => 'nullable',
//            'rate_xb' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room Category updated successfully',
        ]);
    }

    public function destroy(RoomCategory $roomCategory)
    {
        $roomCategory->update([
           'is_active'=>!$roomCategory->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Room category updated successfully',
        ]);
    }
}
