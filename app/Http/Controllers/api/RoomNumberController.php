<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\RoomNumber;
use App\Models\RoomCategory;
use App\Models\RoomName;
use App\Models\RoomFloor;
use App\Models\RoomSpecialRate;
use App\Models\RoomBlock;
use PhpParser\Error;

class RoomNumberController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomNumber::with([
            'roomName' => function ($roomNameQuery) {
                $roomNameQuery->with('category');
            },
            'floor'
        ]);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                ->orWhereHas('category', function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', $searchTerm);
                })
                ->orWhereHas('roomName', function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', $searchTerm);
                })
                ->orWhereHas('floor', function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', $searchTerm);
                })
                ->orWhereHas('floor', function ($q) use ($searchTerm) {
                    $q->where('bed', 'LIKE', $searchTerm);
                });
            });
        }

        $rooms = $query->orderBy('name', 'ASC')->paginate($request->get('per_page'));
        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:10|unique:room_numbers,name',
            'category_id' => 'required|exists:room_categories,id',
            'room_name_id' => 'required|exists:room_names,id',
            'floor_id' => 'required|exists:room_floors,id',
            'bed' => 'nullable|in:SGL,DBL,TWN,TPL,4 BED',
            'is_active' => 'required|boolean',
            'is_clean' => 'nullable|boolean',
        ]);

        RoomNumber::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Room created successfully',
        ]);
    }

    public function storeExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $data = Excel::toCollection(null, $request->file('file'))->first();

        if ($data->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Uploaded file is empty!',
            ], 400);
        }

        $existingRooms = RoomNumber::select('name', 'category_id', 'room_name_id', 'floor_id')
        ->get()
        ->keyBy('name')
        ->toArray();

        $categories = RoomCategory::pluck('id', 'name')->toArray();
        $room_names = RoomName::pluck('id', 'name')->toArray();
        $floors = RoomFloor::pluck('id', 'name')->toArray();

        $newRooms = [];
        $updatedRooms = 0;

        foreach ($data as $index => $row) {
            if ($index === 0) continue; // Skip header row

            $name = trim($row[0]);
            $categoryName = trim($row[1]);
            $roomName = trim($row[2]);
            $floorName = trim($row[3]);

            if (!isset($categories[$categoryName])) {
                $category = RoomCategory::firstOrCreate(['name' => $categoryName]);
                $categories[$categoryName] = $category->id;
            }

            if (!isset($room_names[$roomName])) {
                $room_name = RoomName::firstOrCreate(['name' => $roomName]);
                $room_names[$roomName] = $room_name->id;
            }

            if (!isset($floors[$floorName])) {
                $floor = RoomFloor::firstOrCreate(['name' => $floorName]);
                $floors[$floorName] = $floor->id;
            }

            if (isset($existingRooms[$name])) {
                $room = $existingRooms[$name];

                if (
                    $room['category_id'] !== $categories[$categoryName] ||
                    $room['room_name_id'] !== $room_names[$roomName] ||
                    $room['floor_id'] !== $floors[$floorName]
                ) {
                    RoomNumber::where('name', $name)->update([
                        'category_id' => $categories[$categoryName],
                        'room_name_id' => $room_names[$roomName],
                        'floor_id' => $floors[$floorName],
                    ]);
                    $updatedRooms++;
                }
            } else {
                $newRooms[] = [
                    'name' => $name,
                    'category_id' => $categories[$categoryName],
                    'room_name_id' => $room_names[$roomName],
                    'floor_id' => $floors[$floorName],
                    'is_active' => 1,
                    'is_clean' => 1,
                ];
            }
        }

        if (!empty($newRooms)) {
            RoomNumber::insert($newRooms);
        }

        return response()->json([
            'success' => true,
            'message' => count($newRooms) . ' Room(s) created, ' . $updatedRooms . ' Room(s) updated successfully',
        ]);
    }

    public function update(Request $request, $roomNumber)
    {
//        return response()->json($request->all());
        $item = RoomNumber::findOrFail($roomNumber);

        $validated = $request->validate([
            'category_id' => 'required|exists:room_categories,id',
            'room_name_id' => 'required|exists:room_names,id',
            'floor_id' => 'required|exists:room_floors,id',
            'name' => 'required|max:10|unique:room_numbers,name,' . $item->id,
            'bed' => 'nullable|in:SGL,DBL,TWN,TPL,4 BED',
            'is_active' => 'required|boolean',
            'is_clean' => 'nullable|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room updated successfully',
        ]);
    }

    public function destroy(RoomNumber $roomNumber)
    {
        $roomNumber->update([
            'is_active'=>!$roomNumber->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Room updated successfully',
        ]);
    }

//    public function _getAllRooms(Request $request, User $user)
//    {
//        $rooms = RoomNumber::with('roomName', 'floor')
//            ->where('is_active', 1)
//            ->orderBy('name', 'ASC')
//            ->get()
//            ->map(function ($room) use ($user) {
//                $blocks = RoomBlock::whereHas('details', function ($query) use ($room) {
//                    $query->where('date', '>=', now()->subDay()->toDateString())
//                        ->where('room_id', $room->id);
//                })->with(['details' => function ($query) use ($room) {
//                    $query->where('room_id', $room->id);
//                }, 'user']) // Eager load the user relationship
//                ->get();
//
//                $blockByPerDate = [];
//
//                foreach ($blocks as $block) {
//                    foreach ($block->details as $detail) {
//                        $formattedDate = \Carbon\Carbon::parse($detail->date)->format('d/m/Y');
//
//                        if ($block->user_id === $user->id) {
//                            $blockByPerDate[$formattedDate] = 'self';
//                        } else {
//                            $blockByPerDate[$formattedDate] = [
//                                'blocked_by' => optional($block->user)->name,
//                                'note' => $block->note
//                            ];
//                        }
//                    }
//                }
//
//                $room->block_dates = $blockByPerDate;
//
//                return $room;
//            });
//
//        $room_names = RoomName::where('is_active', 1)->get();
//        $floors = RoomFloor::where('is_active', 1)->get();
//
//        return response()->json([
//            'success' => true,
//            'data' => [
//                'rooms'  => $rooms,
//                'room_names'  => $room_names,
//                'floors' => $floors,
//            ],
//        ]);
//    }
//
//    public function getAllRooms() {
//        $authenticatedUser = auth()->user();
//        if (!$authenticatedUser) throw new Error("UnAuthenticated");
//
//        $rooms = RoomNumber::with([
//                'roomName',
//                'floor',
//                'room_block_details' => function ($roomBlockQuery) {
//                    $roomBlockQuery->with("roomBlock");
//                }
//            ])
//            ->where('is_active', 1)
//            ->orderBy('name', 'ASC')
//            ->get();
//        return response()->json($rooms);
//    }

    public function getAllRooms(Request $request)
    {
        $authenticatedUser = auth()->user();
        if (!$authenticatedUser) throw new Error("UnAuthenticated");

        // Get filter parameters from request
        $categories = $request->query('categories', '');
        $floors = $request->query('floors', '');
        $room_names = $request->query('room_names', '');
        $date_start = $request->query('date_start');
        $date_end = $request->query('date_end');


        // Start building the query
        $query = RoomNumber::with([
            'roomName' => function ($roomNameQuery) {
                $roomNameQuery->with('category');
            },
            'floor',
            'room_block_details' => function ($roomBlockQuery) use ($date_start, $date_end) {
                $roomBlockQuery->with('roomBlock', function ($roomBlockQuery) {
                    $roomBlockQuery->with('user');
                });
                if ($date_start && $date_end) {
                    $roomBlockQuery->whereBetween('date', [$date_start, $date_end]);
                }
            },
            'room_temp_details' => function ($roomTempQuery) use ($date_start, $date_end) {
                $roomTempQuery->with('room_temp', function ($roomTempQuery){
                    $roomTempQuery->with('user');
                });
                if ($date_start && $date_end) {
                    $roomTempQuery->whereBetween('date', [$date_start, $date_end]);
                }
            },
            'room_sale_allocation' => function ($roomSaleAllocationQuery) {
                $roomSaleAllocationQuery->with('saleRoom', function ($roomAllocationSaleQuery) {
                    $roomAllocationSaleQuery->with('user')->select('id', 'user_id', 'code', 'status');
                });
            }
        ])->where('is_active', 1);

        // Apply category filter if provided
        if (!empty($categories)) {
            $categoryIds = explode(',', $categories);
            $query->whereHas('roomName', function($q) use ($categoryIds) {
                $q->whereIn('category_id', $categoryIds);
            });
        }

        // Apply floor filter if provided
        if (!empty($floors)) {
            $floorIds = explode(',', $floors);
            $query->whereIn('floor_id', $floorIds);
        }

        // Apply room name filter if provided
        if (!empty($room_names)) {
            $roomNameIds = explode(',', $room_names);
            $query->whereIn('room_name_id', $roomNameIds);
        }

        // Execute the query and order results
        $rooms = $query->orderBy('name', 'ASC')->get();
        return response()->json($rooms);
    }

    public function searchBySpecialRate(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required',
            'end_date' => 'required',
            'room_name_ids' => 'nullable|array',
            'floor_ids' => 'nullable|array',
        ]);

        $sp_rate = RoomSpecialRate::with('details')
            ->where('start_date', '<=', $request->start_date)
            ->where('end_date', '>=', $request->end_date)
            ->first();

        $user = Auth::user();

        $rooms = [];

        if ($sp_rate && $sp_rate->details->isNotEmpty()) {
            $categoryIds = $sp_rate->details->pluck('category_id')->toArray();
            $floor_ids = $request->floor_ids ?? [];
            $room_name_ids = $request->room_name_ids ?? [];

            $rooms = RoomNumber::with('roomName', 'floor')
                ->whereIn('category_id', $categoryIds)
                ->when(!empty($floor_ids), function ($query) use ($floor_ids) {
                    return $query->whereIn('floor_id', $floor_ids);
                })
                ->when(!empty($room_name_ids), function ($query) use ($room_name_ids) {
                    return $query->whereIn('room_name_id', $room_name_ids);
                })
                ->where('is_active', 1)
                ->get()
                ->map(function ($room) use ($user) {
                    $blocks = RoomBlock::whereHas('details', function ($query) use ($room) {
                        $query->where('date', '>=', now()->subDay()->toDateString())
                            ->where('room_id', $room->id);
                    })->with(['details' => function ($query) use ($room) {
                        $query->where('room_id', $room->id);
                    }, 'user']) // Eager load the user relationship
                    ->get();

                    $blockByPerDate = [];

                    foreach ($blocks as $block) {
                        foreach ($block->details as $detail) {
                            $formattedDate = \Carbon\Carbon::parse($detail->date)->format('d/m/Y');

                            if ($block->user_id === $user->id) {
                                $blockByPerDate[$formattedDate] = 'self';
                            } else {
                                $blockByPerDate[$formattedDate] = [
                                    'blocked_by' => optional($block->user)->name,
                                    'note' => $block->note
                                ];
                            }
                        }
                    }

                    $room->block_dates = $blockByPerDate;

                    return $room;
                });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rooms'  => $rooms,
            ],
        ]);
    }
}
