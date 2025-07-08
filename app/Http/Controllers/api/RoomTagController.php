<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoomTag;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RoomTagController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomTag::query();

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
        $validated = $request->validate([
            'name' => 'required|max:100|unique:room_tags,name',
            'icon' => 'nullable',
        ]);

        try {
            $iconPath = null;
    
            if ($request->hasFile('icon')) {
                $iconName = Str::uuid() . '.' . $request->file('icon')->extension();
                $iconPath = $request->file('icon')->storeAs('images/tags', $iconName, 'public');
            }
    
            RoomTag::create([
                'name' => $validated['name'],
                'icon' => $iconPath ? 'storage/' . $iconPath : null,
            ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Tag created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $roomTag)
    {
        $item = RoomTag::findOrFail($roomTag);

        // \Log::info();

        $validated = $request->validate([
            'name' => 'required|max:100|unique:room_tags,name,' . $item->id,
            'icon' => 'nullable',
        ]);

        try {
            $iconPath = $item->icon;

            if ($request->hasFile('icon')) {
                if ($item->icon) {
                    $oldIconPath = parse_url($item->icon, PHP_URL_PATH);
                    $oldIconPath = str_replace('/storage/', '', $oldIconPath);
                    Storage::disk('public')->delete($oldIconPath);
                }

                $iconName = Str::uuid() . '.' . $request->file('icon')->extension();
                $iconPath = $request->file('icon')->storeAs('images/tags', $iconName, 'public');
        
                $iconPath = 'storage/' . $iconPath;
            } else {
                $iconPath = str_replace(url('/'), '', $iconPath);
                $iconPath = ltrim($iconPath, '/');
            }

            $item->update([
                'name' => $validated['name'],
                'icon' => $iconPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tag updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(RoomTag $roomTag)
    {
        if ($roomTag->icon) {
            $iconPath = parse_url($roomTag->icon, PHP_URL_PATH);
            $iconPath = str_replace('/storage/', '', $iconPath);
            Storage::disk('public')->delete($iconPath);
        }
        $roomTag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully',
        ]);
    }
}
