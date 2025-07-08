<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\RoomBlock;
use App\Models\RoomNumber;
use App\Models\RoomBlockDetail;
use App\Models\User;
use Mpdf\Mpdf;
use PhpParser\Error;


class RoomBlockController extends Controller
{
    public function index(Request $request, User $user)
    {
        $query = RoomBlock::where('user_id', $user->id)->with('user');

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';

            $query->where(function ($q) use ($search) {
                $q->where('note', 'LIKE', $search)
                  ->orWhere('created_at', 'LIKE', $search);
            });
        }

        $data = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function block(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'note' => 'required|max:255',
                'blocks' => 'required|string',
                'unblocks' => 'required|string',
            ]);

            $room_block = RoomBlock::create([
                'user_id' => $validated['user_id'],
                'note' => $validated['note'],
                'task' => json_encode([
                    'blocks' => $validated['blocks'] ? json_decode($validated['blocks'], true) : [],
                    'unblocks' => $validated['unblocks'] ? json_decode($validated['unblocks'], true) : [],
                ]),
            ]);

            // Log::info($validated['blocks']);


            // unblock
            $unblocks = json_decode($validated['unblocks'], true);
            if (!is_array($unblocks)) {
                throw new \Exception('Invalid rooms format in unblock. Expected JSON array.');
            }
            foreach ($unblocks as $data) {
                $formattedDate = Carbon::createFromFormat('d/m/Y', $data['date'])->format('Y-m-d');
                $roomIds = RoomNumber::whereIn('name', $data['room_numbers'])->pluck('id')->toArray();

                RoomBlockDetail::whereIn('room_id', $roomIds)
                    ->where('date', $formattedDate)
                    ->delete();
            }

            // block
            $blocks = json_decode($validated['blocks'], true);
            if (!is_array($blocks)) {
                throw new \Exception('Invalid rooms format in blocks. Expected JSON array.');
            }
            foreach ($blocks as $data) {
                $formattedDate = Carbon::createFromFormat('d/m/Y', $data['date'])->format('Y-m-d');
                $roomIds = RoomNumber::whereIn('name', $data['room_numbers'])->pluck('id')->toArray();

                // Check if any room is already blocked by another user
                $alreadyBlockedRooms = RoomBlockDetail::whereIn('room_id', $roomIds)
                ->where('date', $formattedDate)
                ->where('room_block_id', '!=', $room_block->id)
                ->pluck('room_id')
                ->toArray();

                if (!empty($alreadyBlockedRooms)) {
                    $blockedRoomNames = RoomNumber::whereIn('id', $alreadyBlockedRooms)->pluck('name')->toArray();
                    throw new \Exception('Rooms (' . implode(', ', $blockedRoomNames) . ') already blocked by another user. Remove them and try again.');
                }

                foreach ($roomIds as $roomId) {
                    RoomBlockDetail::create([
                        'room_block_id' => $room_block->id,
                        'room_id' => $roomId,
                        'date' => $formattedDate,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Room block successful',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to block room: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function blockRooms(Request $request)
    {
        $authenticatedUser = auth()->user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'note' => 'required|string',
            'tasks' => 'required|array',
            'task.*.type' => 'required|in:block,unblock',
            'task.*.rooms' => 'required|array',
            'task.*.rooms.*.date' => 'required|date',
            'task.*.rooms.*.id' => 'required|integer',
            'task.*.rooms.*.name' => 'required|string',
        ]);

        $details = [];

        $taskLog = array_reduce($validated['tasks'], function ($acc, $taskItem) use (&$details) {
            $type = $taskItem['type'];

            $roomEntries = array_map(function ($room) use ($type, &$details) {
                $roomId = $room['id'];
                $roomNumber = $room['name'];
                $date = $room['date'];

                if ($type === 'unblock') {
                    RoomBlockDetail::where('room_id', $roomId)
                        ->where('date', $date)
                        ->delete();
                } else {
                    $details[] = [
                        'room_id' => $roomId,
                        'date' => $date,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                return "{$roomNumber}:{$date}";
            }, $taskItem['rooms']);

            $acc[] = [
                'task' => $type,
                'rooms' => $roomEntries,
            ];

            return $acc;
        }, []);

        $roomBlock = RoomBlock::create([
            'user_id' => $authenticatedUser->id,
            'note' => $validated['note'],
            'task' => json_encode($taskLog),
        ]);

        $details = array_map(function ($detail) use ($roomBlock) {
            $detail['room_block_id'] = $roomBlock->id;
            return $detail;
        }, $details);

        if (!empty($details)) {
            RoomBlockDetail::insert($details);
        }

        return response()->json([
            "task" => $taskLog,
            "roomBlockId" => $roomBlock->id,
        ]);
    }


//    public function blockRooms(Request $request) {
//        $authenticatedUser = auth()->user();
//        if (!$authenticatedUser) throw new Error("UnAuthenticated");
//
//        $validated = $request->validate([
//            'note' => 'required|string',
//            'tasks' => 'required|array',
//            'task.*.type' => 'required|in:block,unblock',
//            'task.*.rooms' => 'required|array',
//            'task.*.rooms.*.date' => 'required|date',
//            'task.*.rooms.*.id' => 'required|integer',
//            'task.*.rooms.*.name' => 'required|string',
//        ]);
//
//        $tasks = array_reduce($validated['tasks'], function ($acc, $item) {
//            $acc[] = [
//                'task' => $item['type'],
//                'rooms' => array_reduce($item['rooms'], function ($roomAcc, $roomItem) {
//                    $roomAcc[] = [
//                        "id" => $roomItem["id"],
//                        "number" => $roomItem["name"],
//                        "date" => $roomItem["date"]
//                    ];
//                    return $roomAcc;
//                },[])
//            ];
//            return $acc;
//        }, []);
//
//        $roomBlock = RoomBlock::create([
//            'user_id' => $authenticatedUser->id,
//            'note' => $validated['note'],
//            'task' => json_encode($tasks),
//        ]);
//
//        $details = [];
//
//        foreach ($tasks as $task) {
//            foreach ($task['rooms'] as $room) {
//                if ($task['task'] === 'unblock') {
//                    RoomBlockDetail::where('room_id', $room['id'])
//                        ->where('date', $room['date'])
//                        ->delete();
//                } else {
//                    $details[] = [
//                        'room_block_id' => $roomBlock->id,
//                        'room_id' => $room['id'],
//                        'date' => $room['date'],
//                        'created_at' => now(),
//                        'updated_at' => now(),
//                    ];
//                }
//            }
//        }
//        if (!empty($details)) {
//            RoomBlockDetail::insert($details);
//        }
//
//        $createdRoomBlock = RoomBlockDetail::insert($details);
//
//        return response()->json([ "task" => $tasks, "createdRoomBlock" => $createdRoomBlock ]);
//    }

//        return response()->json($details);
//        return response()->json();
//        foreach ($validated["task"] as $task) {
//            RoomBlockDetail::create([
//                'room_block_id' => $roomBlockEntry->id,
//                'room_id' => $room['id'],
//                'date' => $room['date'],
//            ]);
//        }

    public function blockAll(Request $request)
    {
        // alter all record on this date
        DB::beginTransaction();
        try {

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'date' => 'required',
                'note' => 'nullable',
            ]);

            $room_block = RoomBlock::updateOrCreate(
                ['user_id' => $validated['user_id']],
                ['note' => 'All block']
            );

            $formattedDate = Carbon::createFromFormat('d/m/Y', $validated['date'])->format('Y-m-d');

            // delete others block on this date
            $othersRoomIds = RoomBlockDetail::where('date', $formattedDate)
            ->where('room_block_id', '!=', $room_block->id)
            ->pluck('room_id')
            ->toArray();
            RoomBlockDetail::where('date', $formattedDate)->whereIn('room_id', $othersRoomIds)->delete();

            // find self block on this date
            $selfRoomIds = RoomBlockDetail::where('date', $formattedDate)
            ->where('room_block_id', $room_block->id)
            ->pluck('room_id')
            ->toArray();

            RoomBlockDetail::where('date', $formattedDate)->whereIn('room_id', $selfRoomIds)->delete();

            $allRooms = RoomNumber::pluck('name')->toArray();
            $selfRoomNumbers = RoomNumber::whereIn('id', $selfRoomIds)
                ->pluck('name')
                ->toArray();
            $othersRoomNumbers = RoomNumber::whereIn('id', $othersRoomIds)
                ->pluck('name')
                ->toArray();

            $missingRoomNumbers = array_diff($allRooms, $selfRoomNumbers, $othersRoomNumbers);
            $missingRoomIds = RoomNumber::whereIn('name', $missingRoomNumbers)
                ->pluck('id')
                ->toArray();

            // need not to check if any room is already blocked by another user. as updated room_id retrived before
            foreach ($missingRoomIds as $roomId) {
                RoomBlockDetail::create([
                    'room_block_id' => $room_block->id,
                    'date' => $formattedDate,
                    'room_id' => $roomId,
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'All room blocked successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to block all room: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function blockDetail(Request $request, $id)
    {
        if(!$id){
            return response()->json([
                'success' => false,
                'message' => 'Select a block',
            ]);
        }

        $room_block = RoomBlock::where('id', $id)->with('user')->first();

        if ($room_block && $room_block->task) {
            $taskData = json_decode($room_block->task, true);

            $blocks = $taskData['blocks'] ?? [];
            $unblocks = $taskData['unblocks'] ?? [];

            $blockRooms = [];
            foreach ($blocks as $block) {
                $rooms = RoomNumber::whereIn('name', $block['room_numbers'])->with('roomName')->get();
                $blockRooms[] = [
                    'date' => $block['date'],
                    'rooms' => $rooms
                ];
            }

            $unblockRooms = [];
            foreach ($unblocks as $unblock) {
                $rooms = RoomNumber::whereIn('name', $unblock['room_numbers'])->with('roomName')->get();
                $unblockRooms[] = [
                    'date' => $unblock['date'],
                    'rooms' => $rooms
                ];
            }

            return response()->json([
                'success' => true,
                'blocks' => $blockRooms,
                'unblocks' => $unblockRooms,
                'room_block' => $room_block,
                'logo' => asset('storage/images/logo/logo.png'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No task data found',
        ]);
    }

    public function printBlockDetail(Request $request, $id)
    {
        if(!$id){
            return response()->json([
                'success' => false,
                'message' => 'Select a block',
            ]);
        }

        $room_block = RoomBlock::where('id', $id)->with('user')->first();

        if ($room_block && $room_block->task) {
            $taskData = json_decode($room_block->task, true);

            $blocks = $taskData['blocks'] ?? [];
            $unblocks = $taskData['unblocks'] ?? [];

            $blockRooms = [];
            foreach ($blocks as $block) {
                $rooms = RoomNumber::whereIn('name', $block['room_numbers'])->with('roomName')->get();
                $blockRooms[] = [
                    'date' => $block['date'],
                    'rooms' => $rooms
                ];
            }

            $unblockRooms = [];
            foreach ($unblocks as $unblock) {
                $rooms = RoomNumber::whereIn('name', $unblock['room_numbers'])->with('roomName')->get();
                $unblockRooms[] = [
                    'date' => $unblock['date'],
                    'rooms' => $rooms
                ];
            }

            $data['blocks'] = $blockRooms;
            $data['unblocks'] = $unblockRooms;
            $data['room_block'] = $room_block;
            $data['logo'] = asset('storage/images/logo/logo.png');

            $html = View::make('pdf.room-block-detail', $data)->render();

            $mpdf = new Mpdf();
            $mpdf->WriteHTML($html);

            return response($mpdf->Output('', 'S'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="block-detail.pdf"');

        }

        return response()->json([
            'success' => false,
            'message' => 'No task data found',
        ]);
    }
}
