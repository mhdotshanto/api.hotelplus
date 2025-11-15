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
        $query = RoomSpecialRate::with("details");

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('start_date', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('end_date', 'LIKE', '%' . $request->search . '%');
            });
        }

        $query->orderBy('priority', 'asc');

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
            'note' => 'required|string',
            'priority' => 'required|integer',
            'spacial_rate_details' => 'nullable|array',
            'spacial_rate_details.*.room_name_id' => 'required|integer',
            'spacial_rate_details.*.rate' => 'required|numeric',
            'spacial_rate_details.*.rate_xb' => 'nullable|numeric',
        ]);

        // Check overlapping date range
//        $overlappingRecord = RoomSpecialRate::where(function ($query) use ($validatedData) {
//            $query->whereBetween('start_date', [$validatedData['start_date'], $validatedData['end_date']])
//                ->orWhereBetween('end_date', [$validatedData['start_date'], $validatedData['end_date']])
//                ->orWhere(function ($q) use ($validatedData) {
//                    $q->where('start_date', '<=', $validatedData['start_date'])
//                        ->where('end_date', '>=', $validatedData['end_date']);
//                });
//        })->exists();
//
//        if ($overlappingRecord) {
//            return response()->json([
//                'success' => false,
//                'message' => 'Already has a special rate within this date range.',
//            ]);
//        }

        // Create main record
        $roomSpecialRate = RoomSpecialRate::create([
            'name' => $validatedData['name'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'note' => $validatedData['note'],
            'priority' => $validatedData['priority'],
        ]);

        // Create detail records
        if (!empty($validatedData['spacial_rate_details'])) {
            foreach ($validatedData['spacial_rate_details'] as $rate) {
                RoomSpecialRateDetail::create([
                    'special_rate_id' => $roomSpecialRate->id,
                    'room_name_id' => $rate['room_name_id'],
                    'rate' => $rate['rate'],
                    'rate_xb' => $rate['rate_xb'] ?? 0,
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

    public function update(Request $request, $id)
    {
        $item = RoomSpecialRate::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'note' => 'required|string',
            'priority' => 'required|integer',
            'spacial_rate_details' => 'nullable|array',
            'spacial_rate_details.*.room_name_id' => 'required|integer',
            'spacial_rate_details.*.rate' => 'required|numeric',
            'spacial_rate_details.*.rate_xb' => 'required|numeric',
        ]);

        // Prevent overlapping dates
//        $overlappingRecord = RoomSpecialRate::where('id', '!=', $item->id)
//            ->where(function ($query) use ($validatedData) {
//                $query->whereBetween('start_date', [$validatedData['start_date'], $validatedData['end_date']])
//                    ->orWhereBetween('end_date', [$validatedData['start_date'], $validatedData['end_date']])
//                    ->orWhere(function ($q) use ($validatedData) {
//                        $q->where('start_date', '<=', $validatedData['start_date'])
//                            ->where('end_date', '>=', $validatedData['end_date']);
//                    });
//            })->exists();
//
//        if ($overlappingRecord) {
//            return response()->json([
//                'success' => false,
//                'message' => 'Already has a special rate within this date range.',
//            ]);
//        }

        // Update main record
        $item->update([
            'name' => $validatedData['name'],
            'start_date' => $validatedData['start_date'],
            'end_date' => $validatedData['end_date'],
            'note' => $validatedData['note'],
            'priority' => $validatedData['priority'],
        ]);

        // Replace details
        $item->details()->delete();

        if (!empty($validatedData['spacial_rate_details'])) {
            foreach ($validatedData['spacial_rate_details'] as $rate) {
                RoomSpecialRateDetail::create([
                    'special_rate_id' => $item->id,
                    'room_name_id' => $rate['room_name_id'],
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
