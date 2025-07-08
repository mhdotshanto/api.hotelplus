<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\RoomCategory;
use App\Models\RoomSpecialRate;
use App\Models\RoomSpecialRateDetail;

class RoomSpecialRateController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomSpecialRate::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('start_date', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('end_date', 'LIKE', '%' . $request->search . '%');
            });
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
            'name' => 'required|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'note' => 'required',
            'special_rate' => 'nullable|array',
            'special_rate.*.category_id' => 'required|integer|exists:room_categories,id',
            'special_rate.*.rate' => 'required|numeric',
            'special_rate.*.rate_xb' => 'required|numeric',
        ]);

        // Check if the new date range overlaps with any existing record
        $overlappingRecord = RoomSpecialRate::where(function ($query) use ($validatedData) {
            $query->whereBetween('start_date', [$validatedData['start_date'], $validatedData['end_date']])
                ->orWhereBetween('end_date', [$validatedData['start_date'], $validatedData['end_date']])
                ->orWhere(function ($q) use ($validatedData) {
                    $q->where('start_date', '<=', $validatedData['start_date'])
                        ->where('end_date', '>=', $validatedData['end_date']);
                });
        })->exists();

        if ($overlappingRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Already has a special rate within this date range.',
            ]);
        }

        $roomSpecialRate = RoomSpecialRate::create([
            'name' => $validatedData['name'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'note' => $validatedData['note'],
        ]);
    
        if (!empty($validatedData['special_rate'])) {
            foreach ($validatedData['special_rate'] as $rate) {
                RoomSpecialRateDetail::create([
                    'special_rate_id' => $roomSpecialRate->id,
                    'category_id' => $rate['category_id'],
                    'rate' => $rate['rate'],
                    'rate_xb' => $rate['rate_xb'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Room special rate created successfully',
        ]);
    }

    public function edit(Request $request, $id)
    {
        $data['special_rate'] = RoomSpecialRate::with('details')->find($id);
        $data['categories'] = RoomCategory::all();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Special rate not found',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function update(Request $request, $roomSpecialRate)
    {
        $item = RoomSpecialRate::findOrFail($roomSpecialRate);
        
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'note' => 'required',
            'special_rate' => 'nullable|array',
            'special_rate.*.category_id' => 'required|integer|exists:room_categories,id',
            'special_rate.*.rate' => 'required|numeric',
            'special_rate.*.rate_xb' => 'required|numeric',
        ]);
    
        // Check if the new date range overlaps with any other existing record (excluding the current one)
        $overlappingRecord = RoomSpecialRate::where('id', '!=', $item->id)
            ->where(function ($query) use ($validatedData) {
                $query->whereBetween('start_date', [$validatedData['start_date'], $validatedData['end_date']])
                    ->orWhereBetween('end_date', [$validatedData['start_date'], $validatedData['end_date']])
                    ->orWhere(function ($q) use ($validatedData) {
                        $q->where('start_date', '<=', $validatedData['start_date'])
                            ->where('end_date', '>=', $validatedData['end_date']);
                    });
            })->exists();
    
        if ($overlappingRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Already has a special rate within this date range.',
            ]);
        }
    
        $item->update([
            'name' => $validatedData['name'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'note' => $validatedData['note'],
        ]);
    
        if (!empty($validatedData['special_rate'])) {
            $item->details()->delete();
    
            foreach ($validatedData['special_rate'] as $rate) {
                RoomSpecialRateDetail::create([
                    'special_rate_id' => $item->id,
                    'category_id' => $rate['category_id'],
                    'rate' => $rate['rate'],
                    'rate_xb' => $rate['rate_xb'],
                ]);
            }
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Room special rate updated successfully',
        ]);
    }

    public function destroy(RoomSpecialRate $roomSpecialRate)
    {
        $roomSpecialRate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room special rate deleted successfully',
        ]);
    }
}
