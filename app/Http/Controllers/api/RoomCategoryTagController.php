<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomCategoryTag;
use App\Models\RoomCategory;
use App\Models\RoomTag;

class RoomCategoryTagController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomCategoryTag::with('category', 'tag');

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
    
            $query->whereHas('category', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm);
            })->orWhereHas('tag', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm);
            });
        }

        $data['category_tags'] = $query->paginate(10);
        $data['categories'] = RoomCategory::all();
        $data['tags'] = RoomTag::all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:room_categories,id',
            'tag_id' => 'required|exists:room_tags,id',
        ]);

        RoomCategoryTag::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category tag created successfully',
        ]);
    }

    public function update(Request $request, $roomCategoryTag)
    {
        $item = RoomCategoryTag::findOrFail($roomCategoryTag);
        
        $validated = $request->validate([
            'category_id' => 'required|exists:room_categories,id',
            'tag_id' => 'required|exists:room_tags,id',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category tag updated successfully',
        ]);
    }

    public function destroy(RoomCategoryTag $roomCategoryTag)
    {
        $roomCategoryTag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category tag deleted successfully',
        ]);
    }
}
