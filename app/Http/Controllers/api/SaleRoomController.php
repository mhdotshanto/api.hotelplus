<?php

namespace App\Http\Controllers\api;

use App\Models\AccLedger;
use App\Models\AccVoucher;
use App\Services\PaymentService;
use App\Traits\SaleRoomTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreSaleRequest;
use Exception;
use Carbon\Carbon;
use App\Models\RoomNumber;
use App\Models\RoomName;
use App\Models\RoomFloor;
use App\Models\User;
use App\Models\Country;
use App\Models\Preference;
use App\Models\RoomTemp;
use App\Models\RoomTempDetail;
use App\Models\RoomBlockDetail;
use App\Models\RoomTempDetailXb;
use App\Models\Guest;
use App\Models\Config;
use App\Models\SaleRoom;
use App\Models\SaleRoomAllocation;
use App\Models\SaleRoomCategory;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Services\ReportServices\MpdfService;
use App\Traits\Formatter;
class SaleRoomController extends Controller
{
    use SaleRoomTrait,Formatter;
    protected $mpdfService;
    protected $paymentService;

    public function __construct(MpdfService $mpdfService, PaymentService $paymentService)
    {
        $this->mpdfService = $mpdfService;
        $this->paymentService = $paymentService;
    }

    public function saleCreate(Request $request, User $user)
    {
        try {
            $rooms = RoomNumber::with('roomName', 'floor')
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->get();

            $room_names = RoomName::where('is_active', 1)->get();
            $floors = RoomFloor::where('is_active', 1)->get();
            $countries = Country::all();
            $users = User::where('is_ref', 1)->get();
            $preferences = Preference::all();

            return response()->json([
                'success' => true,
                'data' => [
                    'rooms'  => $rooms,
                    'room_names'  => $room_names,
                    'floors' => $floors,
                    'countries' => $countries,
                    'users' => $users,
                    'preferences' => $preferences,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function saleBlock(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'sale_id' => 'nullable',
                'room_number_id' => 'required|exists:room_numbers,id',
                'room_category_id' => 'required|exists:room_categories,id',
                'room_name_id' => 'required|exists:room_names,id',
                'date' => 'required',
            ]);

            $sale_id = null;

            if ($request->filled('sale_id') && $request->sale_id !== 'null') {

                $room_temp = RoomTemp::where('id', $request->sale_id)->first();

                if (!$room_temp) {
                    throw new \Exception('Sale not found for the given sale_id.');
                }

                $sale_id = $room_temp->id;
                $room_temp->touch(); // only updates `updated_at`
                RoomTempDetail::create([
                    'room_temp_id' => $room_temp->id,
                    'room_number_id' => $validated['room_number_id'],
                    'room_category_id' => $validated['room_category_id'],
                    'room_name_id' => $validated['room_name_id'],
                    'date' => $validated['date'],
                ]);
            } else {
                $new_temp = RoomTemp::create([
                    'user_id' => $validated['user_id'],
                ]);
                $sale_id = $new_temp->id;

                RoomTempDetail::create([
                    'room_temp_id' => $new_temp->id,
                    'room_number_id' => $validated['room_number_id'],
                    'room_category_id' => $validated['room_category_id'],
                    'room_name_id' => $validated['room_name_id'],
                    'date' => $validated['date'],
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Room hold successful',
                'sale_id' => $sale_id,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to hold room',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createSession($userId, $saleId = null) {
        $timestamp = Carbon::now()->valueOf();
        $sessionId = (int) ($timestamp . $userId);
        $payload = ['id' => $sessionId, 'user_id' => $userId];
        if ($saleId) {
            $payload['sale_id'] = $saleId;
        }
        $room_temp = RoomTemp::create($payload);
        return $room_temp->id;
    }

    public function session(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) throw new UnauthorizedHttpException('', 'Unauthenticated');

            $validated = $request->validate([
                'session_id' => ['nullable', 'numeric'],
                'sale_id' => ['nullable', 'numeric']
            ]);
            $sessionId = $validated['session_id'] ?? null;
            $saleId = $validated['sale_id'] ?? null;

            if ($sessionId) {
                $session = RoomTemp::where('id', $sessionId)->where('user_id', $user->id)->first();

                if ($session) {
                    $session->touch();
                    return response()->json([
                        'session_id' => (string) $session->id,
                        'is_new' => false,
                        'message' => 'Session verified.'
                    ], 200);
                }
            }

            if ($saleId) {
                $saleSession = RoomTemp::where('sale_id', $saleId)->where('user_id', $user->id)->first();
                if ($saleSession) {
                    $saleSession->touch();
                    return response()->json([
                        'session_id' => (string) $saleSession->id,
                        'is_new' => false,
                        'message' => 'Edit session verified.'
                    ], 200);
                }
            }

            $newSessionId = $this->createSession($user->id, $saleId);
            return response()->json([
                'session_id' => (string) $newSessionId,
                'is_new' => true,
                'message' => $sessionId ? 'Previous session expired or not found. New session created.' : 'New session created.'
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to process session',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function roomTempBlock(Request $request)
    {
        $authenticatedUser = auth()->user();
        if (!$authenticatedUser) throw new UnauthorizedHttpException('', 'Unauthenticated');
        $validated = $request->validate([
            "session_id" => ["required", "numeric"],
            "rooms" => ["required", "array"],
            "rooms.*.room_number_id" => ["required"],
            "rooms.*.room_category_id" => ["required"],
            "rooms.*.room_name_id" => ["required"],
            "rooms.*.bed" => ["required", "string", Rule::in(['SGL', 'DBL', '2 DBL', '3 DBL', 'TWN', 'CPL', 'TPL'])],
            "rooms.*.rate" => ["required", "numeric"],
            "rooms.*.date" => ["required", "date"],
        ]);

        DB::beginTransaction();
        try {
            $session = RoomTemp::where('id', $validated['session_id'])->first();
            if (!$session) abort(404, "session not found");

            // Prevent session hijacking / unauthorized modification
            if ($session->user_id !== $authenticatedUser->id) abort(403, "Unauthorized: You do not own this reservation session.");

            $yesterday = Carbon::yesterday()->toDateString();
            $now = now();

            // 1. Extract unique room IDs and dates for batch querying (O(1) database queries)
            $requestedRooms = collect($validated['rooms']);
            $roomIds = $requestedRooms->pluck('room_number_id')->unique()->values()->all();
            $dates = $requestedRooms->pluck('date')->unique()->values()->all();

            // 2. Batch Query: All active temp holds for these rooms & dates
            $existingHolds = RoomTempDetail::with(['room_temp.user', 'room:id,name'])
                ->whereIn('room_number_id', $roomIds)
                ->whereIn('date', $dates)
                ->get();

            $otherHoldsMap = [];
            $selfHoldsMap = [];

            foreach ($existingHolds as $hold) {
                $key = "{$hold->room_number_id}_{$hold->date}";
                if ($hold->room_temp_id != $session->id) {
                    $otherHoldsMap[$key] = $hold;
                } else {
                    $selfHoldsMap[$key] = true;
                }
            }

            // 3. Batch Query: All maintenance blocks for these rooms & dates
            $maintenanceBlocksMap = RoomBlockDetail::with(['roomBlock.user'])
                ->whereIn('room_id', $roomIds)
                ->whereIn('date', $dates)
                ->get()
                ->keyBy(fn($b) => "{$b->room_id}_{$b->date}");

            // 4. Batch Query: All confirmed allocations (excluding current sale if editing)
            $confirmedSalesMap = SaleRoomAllocation::with(['saleRoom.user'])
                ->whereIn('room_number_id', $roomIds)
                ->whereIn('date', $dates)
                ->when($session->sale_id, fn($q) => $q->where('room_sale_id', '!=', $session->sale_id))
                ->get()
                ->keyBy(fn($s) => "{$s->room_number_id}_{$s->date}");

            // 5. Room Names Map for fast O(1) label lookup
            $roomNamesMap = RoomNumber::whereIn('id', $roomIds)->pluck('name', 'id')->toArray();

            $temp_details = [];
            $conflicted_rooms = [];
            $seenInPayload = [];

            // 6. Fast O(N) In-Memory Evaluation
            foreach ($validated['rooms'] as $room) {
                $key = "{$room['room_number_id']}_{$room['date']}";
                $roomName = $roomNamesMap[$room['room_number_id']] ?? "Room {$room['room_number_id']}";

                // Rule 1: Yesterday Date constraint
                if (Carbon::parse($room['date'])->lt($yesterday)) {
                    $conflicted_rooms[] = [
                        'room_number_id' => $room['room_number_id'],
                        'room_number' => $roomName,
                        'date' => $room['date'],
                        'reason' => 'date_in_past',
                        'message' => "Cannot hold rooms for dates earlier than yesterday ({$yesterday})."
                    ];
                    continue;
                }

                // Rule 2: Maintenance Block Check (O(1))
                if (isset($maintenanceBlocksMap[$key])) {
                    $block = $maintenanceBlocksMap[$key];
                    $blockedByName = $block->roomBlock?->user?->name ?? 'Admin';
                    $conflicted_rooms[] = [
                        'room_number_id' => $room['room_number_id'],
                        'room_number' => $roomName,
                        'date' => $room['date'],
                        'reason' => 'maintenance_blocked',
                        'held_by' => $blockedByName,
                        'message' => "Room {$roomName} on {$room['date']} is blocked for maintenance by {$blockedByName}."
                    ];
                    continue;
                }

                // Rule 3: Confirmed Booking Check (O(1))
                if (isset($confirmedSalesMap[$key])) {
                    $sale = $confirmedSalesMap[$key];
                    $saleCode = $sale->saleRoom?->code ?? 'Confirmed Sale';
                    $conflicted_rooms[] = [
                        'room_number_id' => $room['room_number_id'],
                        'room_number' => $roomName,
                        'date' => $room['date'],
                        'reason' => 'already_booked',
                        'message' => "Room {$roomName} on {$room['date']} is already booked (#{$saleCode})."
                    ];
                    continue;
                }

                // Rule 4: Anti-collision Cross-Session Temp Hold Check (O(1))
                if (isset($otherHoldsMap[$key])) {
                    $hold = $otherHoldsMap[$key];
                    $heldByName = $hold->room_temp?->user?->name ?? 'another user';
                    $conflicted_rooms[] = [
                        'room_number_id' => $room['room_number_id'],
                        'room_number' => $roomName,
                        'date' => $room['date'],
                        'reason' => 'held_by_other',
                        'held_by' => $heldByName,
                        'message' => "Room {$roomName} on {$room['date']} is already held by {$heldByName}."
                    ];
                    continue;
                }

                // Rule 5: Avoid duplicate insert in the same session or within the current payload batch
                if (isset($selfHoldsMap[$key]) || isset($seenInPayload[$key])) {
                    continue;
                }

                $seenInPayload[$key] = true;

                $temp_details[] = [
                    'room_temp_id' => $session->id,
                    'room_number_id' => $room['room_number_id'],
                    'room_category_id' => $room['room_category_id'],
                    'room_name_id' => $room['room_name_id'],
                    'bed' => $room['bed'],
                    'rate' => $room['rate'],
                    'date' => $room['date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // If single room requested and it was conflicted, return 409 conflict error
            if (count($validated['rooms']) === 1 && count($conflicted_rooms) > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => $conflicted_rooms[0]['message'],
                    'conflicted_rooms' => $conflicted_rooms,
                ], 409);
            }

            if (!empty($temp_details)) {
                RoomTempDetail::insert($temp_details);
                
                // Fetch newly inserted details with their real database IDs
                $insertedDetails = RoomTempDetail::where('room_temp_id', $session->id)
                    ->whereIn('room_number_id', array_column($temp_details, 'room_number_id'))
                    ->whereIn('date', array_column($temp_details, 'date'))
                    ->get();
            } else {
                $insertedDetails = collect();
            }

            $session->touch();
            DB::commit();

            return response()->json([
                'success' => true,
                'room_temp' => $session,
                'held_count' => count($temp_details),
                'skipped_count' => count($conflicted_rooms),
                'held_rooms' => $insertedDetails,
                'skipped_rooms' => $conflicted_rooms,
                'message' => count($conflicted_rooms) > 0
                    ? count($temp_details) . " rooms held. " . count($conflicted_rooms) . " rooms skipped (already held/occupied/blocked)."
                    : "Rooms held successfully."
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function roomTempReleaseDate(Request $request)
    {
        $authenticatedUser = auth()->user();
        if (!$authenticatedUser) throw new UnauthorizedHttpException('', 'Unauthenticated');
        $validated = $request->validate([
            "session_id" => ["required", "numeric"],
            "date" => ["required", "date"],
        ]);

        DB::beginTransaction();
        try {
            $session = RoomTemp::where('id', $validated['session_id'])->where('user_id', $authenticatedUser->id)->first();
            if (!$session) abort(404, "session not found");

            RoomTempDetail::where('room_temp_id', $session->id)
                ->where('date', $validated['date'])
                ->delete();

            RoomTempDetailXb::where('room_temp_id', $session->id)
                ->where('date', $validated['date'])
                ->delete();

            $session->touch();
            DB::commit();
            return response()->json(['success' => true, 'message' => "Rooms released for date {$validated['date']}."]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyRoomTempDetail(Request $request, $roomTempDetail)
    {
        $detail = is_numeric($roomTempDetail) ? RoomTempDetail::find($roomTempDetail) : null;
        if ($detail) {
            $detail->room_temp?->touch();
            $detail->delete();
            return response()->json(['success' => true, 'message' => 'Deleted successfully']);
        }

        // Fallback: Delete by session_id + room_number_id + date if ID is temporary or not found
        $sessionId = $request->query('session_id') ?? $request->input('session_id');
        $roomNumberId = $request->query('room_number_id') ?? $request->input('room_number_id');
        $date = $request->query('date') ?? $request->input('date');

        if ($sessionId && $roomNumberId && $date) {
            $deleted = RoomTempDetail::where('room_temp_id', $sessionId)
                ->where('room_number_id', $roomNumberId)
                ->where('date', $date)
                ->delete();

            if ($deleted) {
                RoomTemp::where('id', $sessionId)->touch();
                return response()->json(['success' => true, 'message' => 'Deleted by room and date successfully']);
            }
        }

        return response()->json(['success' => true, 'message' => 'Released successfully']);
    }

    public function saleRelease(Request $request)
    {
        try {
            $validated = $request->validate([
                'sale_id' => 'required|exists:room_temps,id',
                'room_number_id' => 'required|exists:room_numbers,id',
                'date' => 'required|date',
            ]);

            $room_temp = RoomTemp::where('id', $validated['sale_id'])->first();

            if ($room_temp) {
                RoomTempDetail::where('room_temp_id', $room_temp->id)
                    ->where('date', $validated['date'])
                    ->where('room_number_id', $validated['room_number_id'])
                    ->delete();

                // delete this rooms extra bed if exist
                RoomTempDetailXb::where('room_temp_id', $room_temp->id)
                    ->where('date', $validated['date'])
                    ->where('room_number_id', $validated['room_number_id'])
                    ->delete();

                $room_temp->touch(); // only update `updated_at`

                return response()->json([
                    'success' => true,
                    'message' => 'Release successful',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No hold rooms found',
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to release room',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function salesInfo(Request $request)
    {
        $configs = Config::all();
        $roomTemps = RoomTemp::with('details', 'user')->get();

        $self_temp_blocks = [];
        $self_temp_block_rooms = [];
        $other_temp_blocks = [];
        $guest = null;
        $max_blocked_rooms = [];
        $extra_beds = [];
        $extra_beds_bydate = [];
        $blocks = [];
        $sale_allocations = [];
        $sale_info = [];
        $yesterday = Carbon::yesterday()->toDateString();

        // sale allocations data adding
        $sale_allocations = SaleRoomAllocation::with(['saleRoom:id,status,code'])->get(['sale_id', 'room_id', 'date', 'is_xb']);

        // room_temps data adding
        if ($request->filled('id') && $request->id !== 'null') {
            $roomTemp = null;

            if ($request->filled('sid') && $request->sid !== 'null') {
                $roomTemp = $roomTemps->where('id', $request->id)->where('sale_id', $request->sid)->first();
                if ($roomTemp) {
                    if ($roomTemp->user_id !== Auth::id()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This sale is updating by ' . $roomTemp->user->name,
                            'self_temp_blocks' => $self_temp_blocks,
                            'other_temp_blocks' => $other_temp_blocks,
                            'blocks' => $blocks,
                            'guest' => $guest,
                            'configs' => $configs,
                            'max_blocked_rooms' => $max_blocked_rooms,
                            'self_temp_block_rooms' => $self_temp_block_rooms,
                            'extra_beds' => $extra_beds,
                            'extra_beds_bydate' => $extra_beds_bydate,
                            'sale_allocations' => $sale_allocations,
                            'sale_info' => $sale_info,
                        ]);
                    }
                }

                $sale_info = SaleRoom::select(
                    'id',
                    'price',
                    'price_gross',
                    'discount_type',
                    'discount_per',
                    'discount_amount',
                    'price_net',
                    'vat_per',
                    'servicecharge_per',
                    'date_to_confirm',
                    'guest_qty',
                    'guest_name_first',
                    'guest_name_last',
                    'guest_mobile',
                    'eta',
                    'etd',
                    'master_room'
                )
                    ->with('roomCategories.category', 'masterRoom')
                    ->where('id', $request->sid)
                    ->first();

                if ($sale_info) {
                    $sale_info->eta = Carbon::parse($sale_info->eta)->format('h:i A');
                    $sale_info->etd = Carbon::parse($sale_info->etd)->format('h:i A');
                }
            } else {
                $roomTemp = $roomTemps->where('id', $request->id)->whereNull('sale_id')->first();
            }

            if ($roomTemp) {

                // guest info
                $guest = Guest::find($roomTemp->guest_id);
                if ($guest) {
                    $guest->preference_id = json_decode($guest->preference_id);
                }

                // rooms that are block max days
                $records = RoomTempDetail::where('room_temp_id', $roomTemp->id)
                    ->where('date', '>=', $yesterday)
                    ->select('date', 'room_number_id')
                    ->orderBy('date')
                    ->get()
                    ->groupBy('date');

                $roomIdsByDate = [];
                foreach ($records as $date => $items) {
                    $roomIdsByDate[] = $items->pluck('room_number_id')->unique()->values()->toArray();
                }

                $maxBlockedRoomIds = [];
                $frequency = [];

                foreach ($roomIdsByDate as $dateRooms) {
                    foreach (array_unique($dateRooms) as $roomId) {
                        $frequency[$roomId] = ($frequency[$roomId] ?? 0) + 1;
                    }
                }

                if (!empty($frequency)) {
                    $maxCount = max($frequency);
                    $maxBlockedRoomIds = array_keys(array_filter($frequency, function ($count) use ($maxCount) {
                        return $count === $maxCount;
                    }));
                }

                $max_blocked_rooms = RoomNumber::whereIn('id', $maxBlockedRoomIds)->get();

                // self_temp_block_rooms
                $self_temp_block_rooms = RoomTempDetail::with('roomName', 'category')
                    ->where('room_temp_id', $roomTemp->id)
                    ->where('date', '>=', $yesterday)
                    ->get();

                $categoryCounts = $self_temp_block_rooms->countBy('room_category_id');

                $self_temp_block_rooms = $self_temp_block_rooms
                    ->unique('room_category_id')
                    ->map(function ($item) use ($categoryCounts) {
                        $item->qty = $categoryCounts[$item->room_category_id] ?? 1;
                        return $item;
                    })
                    ->values();

                // extra beds
                $extra_beds = RoomTempDetailXb::with('category')
                    ->where('room_temp_id', $roomTemp->id)
                    ->where('date', '>=', $yesterday)
                    ->get();


                $extra_beds_bydate = $extra_beds->map(function ($item) {
                    return [
                        'temp_id'  => $item->room_temp_id,
                        'room_id'  => $item->room_number_id,
                        'date'     => Carbon::parse($item->date)->format('d/m/Y'),
                    ];
                })->toArray();

                $xbCategoryCounts = $extra_beds->countBy('room_category_id');

                $extra_beds = $extra_beds
                    ->unique('room_category_id')
                    ->map(function ($item) use ($xbCategoryCounts) {
                        $item->qty = $xbCategoryCounts[$item->room_category_id] ?? 1;
                        return $item;
                    })
                    ->values();

                foreach ($roomTemps as $block) {
                    foreach ($block->details as $detail) {
                        $parsedDate = Carbon::parse($detail->date);

                        // Skip if date is before yesterday
                        if ($parsedDate->lt($yesterday)) {
                            continue;
                        }

                        $date = $parsedDate->format('d/m/Y');
                        $user = $block->user;
                        $userId = $user->id;

                        if ($block->user_id == Auth::id() && $block->id == $request->id) {
                            // SELF TEMP BLOCK
                            $dateIndex = null;
                            foreach ($self_temp_blocks as $index => $item) {
                                if ($item['date'] === $date) {
                                    $dateIndex = $index;
                                    break;
                                }
                            }

                            if (is_null($dateIndex)) {
                                $self_temp_blocks[] = [
                                    'date' => $date,
                                    'users' => [
                                        [
                                            'room_ids' => [$detail->room_number_id],
                                        ],
                                    ],
                                ];
                            } else {
                                $self_temp_blocks[$dateIndex]['users'][0]['room_ids'][] = $detail->room_number_id;
                            }
                        } else {
                            // OTHER TEMP BLOCK
                            $dateIndex = null;
                            foreach ($other_temp_blocks as $index => $item) {
                                if ($item['date'] === $date) {
                                    $dateIndex = $index;
                                    break;
                                }
                            }

                            if (is_null($dateIndex)) {
                                $other_temp_blocks[] = [
                                    'date' => $date,
                                    'users' => [
                                        [
                                            'id' => $userId,
                                            'name' => $user->name,
                                            'room_ids' => [$detail->room_number_id],
                                        ],
                                    ],
                                ];
                            } else {
                                $userExists = false;
                                foreach ($other_temp_blocks[$dateIndex]['users'] as &$userData) {
                                    if ($userData['id'] === $userId) {
                                        $userData['room_ids'][] = $detail->room_number_id;
                                        $userExists = true;
                                        break;
                                    }
                                }

                                if (!$userExists) {
                                    $other_temp_blocks[$dateIndex]['users'][] = [
                                        'id' => $userId,
                                        'name' => $user->name,
                                        'room_ids' => [$detail->room_number_id],
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // If no room_temp ID found, then treat all as other_temp_blocks
            foreach ($roomTemps as $block) {
                foreach ($block->details as $detail) {
                    $parsedDate = Carbon::parse($detail->date);

                    // Skip if date is before yesterday
                    if ($parsedDate->lt($yesterday)) {
                        continue;
                    }

                    $date = $parsedDate->format('d/m/Y');
                    $user = $block->user;
                    $userId = $user->id;

                    $dateIndex = null;
                    foreach ($other_temp_blocks as $index => $item) {
                        if ($item['date'] === $date) {
                            $dateIndex = $index;
                            break;
                        }
                    }

                    if (is_null($dateIndex)) {
                        $other_temp_blocks[] = [
                            'date' => $date,
                            'users' => [
                                [
                                    'id' => $userId,
                                    'name' => $user->name,
                                    'room_ids' => [$detail->room_number_id],
                                ],
                            ],
                        ];
                    } else {
                        $userExists = false;
                        foreach ($other_temp_blocks[$dateIndex]['users'] as &$userData) {
                            if ($userData['id'] === $userId) {
                                $userData['room_ids'][] = $detail->room_number_id;
                                $userExists = true;
                                break;
                            }
                        }

                        if (!$userExists) {
                            $other_temp_blocks[$dateIndex]['users'][] = [
                                'id' => $userId,
                                'name' => $user->name,
                                'room_ids' => [$detail->room_number_id],
                            ];
                        }
                    }
                }
            }
        }

        // room_blocks data adding
        $details = RoomBlockDetail::with(['roomBlock.user'])->where('date', '>=', $yesterday)->get();
        $grouped = $details->groupBy('date');

        foreach ($grouped as $date => $detailsForDate) {
            $users = [];

            $detailsByUser = $detailsForDate->groupBy(fn($detail) => $detail->roomBlock->user_id);

            foreach ($detailsByUser as $userId => $userDetails) {
                $user = $userDetails->first()->roomBlock->user;

                // Now group by roomBlock ID to get separate notes/created_at per block
                $roomBlocks = $userDetails->groupBy('room_block_id')->map(function ($blockDetails) {
                    $roomBlock = $blockDetails->first()->roomBlock;

                    return [
                        'room_block_id' => $roomBlock->id,
                        'note' => $roomBlock->note,
                        'created_at' => $roomBlock->created_at->format('d/m/Y H:i a'),
                        'room_ids' => $blockDetails->pluck('room_id')->unique()->values()->all(),
                    ];
                })->values();

                $users[] = [
                    'id' => $user->id,
                    'user_name' => $user->user_name,
                    'name' => $user->name,
                    'blocks' => $roomBlocks,
                ];
            }

            $blocks[] = [
                'date' => Carbon::parse($date)->format('d/m/Y'),
                'users' => $users,
            ];
        }

        return response()->json([
            'success' => true,
            'self_temp_blocks' => array_values($self_temp_blocks),
            'other_temp_blocks' => array_values($other_temp_blocks),
            'blocks' => $blocks,
            'guest' => $guest,
            'configs' => $configs,
            'max_blocked_rooms' => $max_blocked_rooms,
            'self_temp_block_rooms' => $self_temp_block_rooms,
            'extra_beds' => $extra_beds,
            'extra_beds_bydate' => $extra_beds_bydate,
            'sale_allocations' => $sale_allocations,
            'sale_info' => $sale_info,
        ]);
    }

    public function toggleRoomXb(Request $request, RoomTempDetail $roomTempDetail)
    {
        $authenticatedUser = auth()->user();
        if (!$authenticatedUser) throw new UnauthorizedHttpException('', 'Unauthenticated');

        if (!$roomTempDetail) abort(400, 'temp_id is required');

        $validated = $request->validate(["rate" => ["required", "numeric"]]);

        $roomTempDetail->update(['has_xb' => !$roomTempDetail->getRawOriginal('has_xb'), 'rate_xb' => $validated['rate']]);

        $roomTempDetail->refresh();

        return response()->json($roomTempDetail);
    }

    public function salesInfoExb(Request $request)
    {
        $yesterday = Carbon::yesterday()->toDateString();
        $rooms = [];
        $sales = [];
        $room_xbs = [];

        if ($request->filled('id') && $request->id !== 'null') {
            $roomTemp = RoomTemp::where('id', $request->id)->first();

            if ($request->filled('sid') && $request->sid !== 'null') {
                if ($roomTemp->sale_id != $request->sid) {
                    return response()->json([
                        'success' => false,
                        'rooms' => $rooms,
                        'sales' => $sales,
                        'room_xbs' => $room_xbs,
                        'message' => 'sale id not match',
                    ]);
                }
            }

            if ($request->sid === 'null' && $roomTemp && !is_null($roomTemp->sale_id)) {
                return response()->json([
                    'success' => false,
                    'rooms' => $rooms,
                    'sales' => $sales,
                    'room_xbs' => $room_xbs,
                    'message' => 'This sale is in updating stage',
                ]);
            }

            $rooms = RoomNumber::with('roomName')
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->get();

            $RoomTempDetail = RoomTempDetail::where('room_temp_id', $request->id)
                ->where('date', '>=', $yesterday)
                ->get();


            $sales = $RoomTempDetail
                ->groupBy('date')
                ->map(function ($items, $date) {
                    return [
                        'date' => \Carbon\Carbon::parse($date)->format('d/m/Y'),
                        'room_ids' => $items->pluck('room_number_id')->map(fn($id) => $id)->unique()->values()->all(),
                    ];
                })
                ->values()
                ->all();

            $detail_xbs = RoomTempDetailXb::where('room_temp_id', $request->id)
                ->where('date', '>=', $yesterday)
                ->get();

            $room_xbs = $detail_xbs
                ->groupBy('date')
                ->map(function ($items, $date) {
                    return [
                        'date' => \Carbon\Carbon::parse($date)->format('d/m/Y'),
                        'room_ids' => $items->pluck('room_number_id')->map(fn($id) => $id)->unique()->values()->all(),
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'rooms' => $rooms,
                'sales' => $sales,
                'room_xbs' => $room_xbs,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'please select a sale',
        ]);
    }

    public function addExtraBed(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:room_temps,id',
            'room_id' => 'required|exists:room_numbers,id',
            'category_id' => 'required|exists:room_categories,id',
            'room_name_id' => 'required|exists:room_names,id',
            'date' => 'required',
        ]);

        $room_temp = RoomTemp::find($request->sale_id);
        if ($room_temp) {
            $room_temp->touch();
        }

        RoomTempDetailXb::create([
            'room_temp_id' => $validated['sale_id'],
            'room_number_id' => $validated['room_id'],
            'room_category_id' => $validated['category_id'],
            'room_name_id' => $validated['room_name_id'],
            'date' => $validated['date'],
        ]);

        $detail_xbs = RoomTempDetailXb::where('room_temp_id', $validated['sale_id'])->get();

        $room_xbs = [];
        $room_xbs = $detail_xbs
            ->groupBy('date')
            ->map(function ($items, $date) {
                return [
                    'date' => \Carbon\Carbon::parse($date)->format('d/m/Y'),
                    'room_ids' => $items->pluck('room_number_id')->map(fn($id) => $id)->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'room_xbs' => $room_xbs,
            'message' => 'Extra bed added successfully',
        ]);
    }

    public function releaseExtraBed(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:room_temps,id',
            'room_id' => 'required|exists:room_numbers,id',
            'date' => 'required',
        ]);

        RoomTempDetailXb::where('room_temp_id', $validated['sale_id'])
            ->where('room_number_id', $validated['room_id'])
            ->where('date', $validated['date'])
            ->delete();

        $detail_xbs = RoomTempDetailXb::where('room_temp_id', $validated['sale_id'])->get();

        $room_xbs = [];
        $room_xbs = $detail_xbs
            ->groupBy('date')
            ->map(function ($items, $date) {
                return [
                    'date' => \Carbon\Carbon::parse($date)->format('d/m/Y'),
                    'room_ids' => $items->pluck('room_number_id')->map(fn($id) => $id)->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'room_xbs' => $room_xbs,
            'message' => 'Extra bed release successfully',
        ]);
    }

    public function storeSale(StoreSaleRequest $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validated();

            if ($validated['sale_id']) {
                SaleRoomAllocation::where('sale_id', $validated['sale_id'])->delete();
                SaleRoomCategory::where('sale_id', $validated['sale_id'])->delete();
            }

            $yesterday = Carbon::yesterday()->toDateString();
            $room_temp = RoomTemp::find($validated['room_temp_id']);

            if (!$room_temp) {
                throw new \Exception('Temp not found for the given temp id.');
            }
            if ($room_temp->guest_id == 0) {
                throw new \Exception('Please add guest info before creating the sale.');
            }
            $guest = Guest::find($room_temp->guest_id);
            if (!$guest) {
                throw new \Exception('guest not found!');
            }

            $roomTempDetails = RoomTempDetail::with('category')
                ->where('room_temp_id', $room_temp->id)
                ->where('date', '>=', $yesterday)
                ->get();

            $roomQty = $roomTempDetails->pluck('room_number_id')->unique()->count();
            $categoryIds = $roomTempDetails->pluck('room_category_id')->unique();

            $price = $roomTempDetails->sum(function ($detail) {
                return $detail->category->rate ?? 0;
            });

            $roomTempDetailXbs = RoomTempDetailXb::with('category')
                ->where('room_temp_id', $room_temp->id)
                ->where('date', '>=', $yesterday)
                ->get();

            $price += $roomTempDetailXbs->sum(function ($detail) use ($categoryIds) {
                return $categoryIds->contains($detail->room_category_id)
                    ? ($detail->category->rate_xb ?? 0)
                    : 0;
            });

            $xbQty = $roomTempDetailXbs->pluck('room_number_id')->unique()->count();

            $configs = Config::whereIn('slug', ['vat', 'service-charge'])->get()->keyBy('slug');
            $vat = $configs['vat']->value ?? 0;
            $serviceCharge = $configs['service-charge']->value ?? 0;

            $vatAmount = ($price * $vat) / 100;
            $servicechargeAmount = ($price * $serviceCharge) / 100;

            $priceGross = $price + $vatAmount + $servicechargeAmount;
            $priceNet = $priceGross;
            $discountAmount = 0;

            if ($validated['discount_type'] == 'invoice') {
                $discountAmount = ($priceGross * $validated['discount_per']) / 100;
                $priceNet = $priceGross - $discountAmount;
            } else if ($validated['discount_type'] == 'category') {
                $sumCategoryDisAmount = collect($validated['categories'])
                    ->sum(function ($item) {
                        return (float) $item['discount_amount'];
                    });
                if (!empty($validated['xbs'])) {
                    $sumXbsDisAmount = collect($validated['xbs'])
                        ->sum(function ($item) {
                            return (float) $item['discount_amount'];
                        });

                    $priceNet = $priceGross - ($sumCategoryDisAmount + $sumXbsDisAmount);
                } else {
                    $priceNet = $priceGross - $sumCategoryDisAmount;
                }
            } else {
                // no discount
                $discountAmount = 0;
                $priceNet = $priceGross;
            }

            $validated['eta'] = Carbon::createFromFormat('h:i A', $validated['eta'])->format('H:i:s');
            $validated['etd'] = Carbon::createFromFormat('h:i A', $validated['etd'])->format('H:i:s');

            $existingSale = $validated['sale_id'] ? SaleRoom::find($validated['sale_id']) : null;

            $sale_room = SaleRoom::updateOrCreate(
                ['id' => $validated['sale_id']],
                [
                    'code' =>  $existingSale?->code ?? str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                    'guest_id' => $guest->id,
                    'guest_name_title' => $guest->name_address,
                    'guest_name_first' => $guest->first_name,
                    'guest_name_last' => $guest->last_name,
                    'guest_mobile' => $guest->mobile,
                    'guest_mobile_emergency' => $guest->emergency_contact,
                    'guest_email' => $guest->email,
                    'guest_company' => $guest->company,
                    'guest_address' => $guest->pres_address,
                    'ref_id' => $guest->reference_id,
                    'user_id' => Auth::id(),
                    'master_room' => $validated['master_room'],
                    'rooms' => implode(',', $validated['rooms']),
                    'guest_qty' => $validated['pax'],
                    'room_qty' => $roomQty,
                    'xb_qty' => $xbQty,
                    'date' => Carbon::today()->format('Y-m-d'),
                    'price' => $price,
                    'vat_per' => $vat,
                    'vat_amount' => $vatAmount,
                    'servicecharge_per' => $serviceCharge,
                    'servicecharge_amount' => $servicechargeAmount,
                    'price_gross' => $priceGross,
                    'discount_type' => $validated['discount_type'],
                    'discount_per' => $validated['discount_per'],
                    'discount_amount' => $discountAmount,
                    'price_sale' => $priceNet,
                    'price_net' => $priceNet,
                    'remarks' => $validated['remark'],
                    'date_checkin' => $validated['date_checkin'],
                    'eta' => $validated['eta'],
                    'date_checkout' => $validated['date_checkout'],
                    'etd' => $validated['etd'],
                    'date_to_confirm' => $validated['confirmation_date'],
                    'status' => 'guaranteed',
                ]
            );

            // sale_room_allocations entry
            foreach ($roomTempDetails as $tmpDetail) {
                SaleRoomAllocation::create([
                    'sale_id' => $sale_room->id,
                    'category_id' => $tmpDetail->room_category_id,
                    'room_id' => $tmpDetail->room_number_id,
                    'date' => $tmpDetail->date,
                    'is_xb' => 0,
                ]);
            }
            foreach ($roomTempDetailXbs as $xb) {
                SaleRoomAllocation::create([
                    'sale_id' => $sale_room->id,
                    'category_id' => $xb->room_category_id,
                    'room_id' => $xb->room_number_id,
                    'date' => $xb->date,
                    'is_xb' => 1,
                ]);
            }

            // sale_room_categories entry
            if ($validated['discount_type'] == 'category') {
                $tempDetailGroup = $roomTempDetails->groupBy(function ($item) {
                    return $item->room_category_id . '_' . $item->date;
                });

                $masterRooms = $tempDetailGroup->map(function ($items) {
                    return [
                        'category_id' => $items->first()->room_category_id,
                        'date' => $items->first()->date,
                        'qty' => $items->count(),
                    ];
                })->values()->toArray();

                $reqCategories = collect($validated['categories']);

                foreach ($masterRooms as $item) {
                    $matchedCategory = $reqCategories->firstWhere('category_id', $item['category_id']);

                    $itemVat = (($matchedCategory['rate'] * $item['qty']) * $vat) / 100;
                    $itemSc = (($matchedCategory['rate'] * $item['qty']) * $serviceCharge) / 100;
                    $itemTotalPrice = ($matchedCategory['rate'] * $item['qty']) + $itemVat + $itemSc;
                    $masterRoomDA = ($itemTotalPrice * $matchedCategory['discount_per']) / 100;

                    SaleRoomCategory::create([
                        'sale_id' => $sale_room->id,
                        'category_id' => $item['category_id'],
                        'date' => $item['date'],
                        'qty' => $item['qty'],
                        'rate' => $matchedCategory['rate'] ?? 0,
                        'is_xb' => 0,
                        'discount_per' => $matchedCategory['discount_per'] ?? 0,
                        'discount_amount' => $masterRoomDA ?? 0,
                        'rate_sale' => $matchedCategory['rate'] ?? 0,
                    ]);
                }

                // extra beds entry
                if (!empty($validated['xbs'])) {
                    $reqXbs = collect($validated['xbs']);

                    $tempDetailXbGroup = $roomTempDetailXbs->groupBy(function ($item) {
                        return $item->room_category_id . '_' . $item->date;
                    });

                    $xbRooms = $tempDetailXbGroup->map(function ($items) {
                        return [
                            'category_id' => $items->first()->room_category_id,
                            'date' => $items->first()->date,
                            'qty' => $items->count(),
                        ];
                    })->values()->toArray();

                    foreach ($xbRooms as $item) {
                        $matchedCategory = $reqXbs->firstWhere('category_id', $item['category_id']);

                        $itemVat = (($matchedCategory['rate'] * $item['qty']) * $vat) / 100;
                        $itemSc = (($matchedCategory['rate'] * $item['qty']) * $serviceCharge) / 100;
                        $itemTotalPrice = ($matchedCategory['rate'] * $item['qty']) + $itemVat + $itemSc;
                        $xbRoomDA = ($itemTotalPrice * $matchedCategory['discount_per']) / 100;

                        SaleRoomCategory::create([
                            'sale_id' => $sale_room->id,
                            'category_id' => $item['category_id'],
                            'date' => $item['date'],
                            'qty' => $item['qty'],
                            'rate' => $matchedCategory['rate'] ?? 0,
                            'is_xb' => 1,
                            'discount_per' => $matchedCategory['discount_per'] ?? 0,
                            'discount_amount' => $xbRoomDA ?? 0,
                            'rate_sale' => $matchedCategory['rate'] ?? 0,
                        ]);
                    }
                }
            }

            // delete temp tables data
            RoomTemp::where('id', $validated['room_temp_id'])->delete();
            RoomTempDetail::where('room_temp_id', $validated['room_temp_id'])->delete();
            RoomTempDetailXb::where('room_temp_id', $validated['room_temp_id'])->delete();


            // $pdfData['logo'] = asset('storage/images/logo/logo.png');

            // $html = View::make('pdf.room-block-detail', $pdfData)->render();

            // $mpdf = new Mpdf();
            // $mpdf->WriteHTML($html);

            // return response($mpdf->Output('', 'S'), 200)
            //     ->header('Content-Type', 'application/pdf')
            //     ->header('Content-Disposition', 'inline; filename="block-detail.pdf"');

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function searchReservation(Request $request)
    {
        $temId = null;
        $saleId = null;
        if ($request->filled('code') && $request->code !== 'null') {
            $saleRoom = SaleRoom::where('code', $request->code)->first();

            $roomTemp = RoomTemp::where('sale_id', $saleRoom->id)->first();
            if ($roomTemp) {
                $temId = $roomTemp->id;
            }

            if ($saleRoom) {
                $saleId = $saleRoom->id;
                $saleRoomAllocations = SaleRoomAllocation::with('room.roomName')->where('sale_id', $saleRoom->id)->get();

                if (!empty($saleRoomAllocations)) {

                    if (!$roomTemp) {
                        $roomTemp = RoomTemp::create([
                            'sale_id' => $saleRoom->id,
                            'user_id' => Auth::id(),
                            'guest_id' => $saleRoom->guest_id,
                        ]);

                        $temId = $roomTemp->id;

                        foreach ($saleRoomAllocations as $sra) {
                            if ($sra->is_xb == 1) {
                                RoomTempDetailXb::create([
                                    'room_temp_id' => $roomTemp->id,
                                    'room_number_id' => $sra->room_id,
                                    'room_category_id' => $sra->category_id,
                                    'room_name_id' => $sra->room->roomName->id,
                                    'date' => $sra->date,
                                ]);
                            } else {
                                RoomTempDetail::create([
                                    'room_temp_id' => $roomTemp->id,
                                    'room_number_id' => $sra->room_id,
                                    'room_category_id' => $sra->category_id,
                                    'room_name_id' => $sra->room->roomName->id,
                                    'date' => $sra->date,
                                ]);
                            }
                        }
                    }
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found by this code',
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'please fillup reservation code',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'reservation found and sended to edit',
            'tem_id' => $temId,
            'sale_id' => $saleId,
        ]);
    }

    public function getRoomAllocations(Request $request)
    {
        $saleRoomAllocations = [];

        if ($request->filled('sid') && $request->sid !== 'null') {
            $saleRoomAllocations = SaleRoomAllocation::where('sale_id', $request->sid)->get();
            return response()->json([
                'success' => true,
                'saleRoomAllocations' => $saleRoomAllocations,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'sale id required',
                'saleRoomAllocations' => $saleRoomAllocations,
            ]);
        }
    }

    public function saleRoom(Request $request)
    {
        try {
            DB::beginTransaction();
            $authenticatedUser = auth()->user();
            if (!$authenticatedUser) throw new UnauthorizedHttpException('', 'Unauthenticated');

            $validated = $request->validate($this->saleRoomRules());

            $temp = RoomTemp::with(['details' => function ($tempDetailsQuery) {
                $tempDetailsQuery->with([
                    'category',
                ]);
            }])->findOrFail($validated['temp_id']);

            $config = Config::pluck('value', 'slug')->toArray();

            $guest = Guest::findOrFail($validated['guest_id']);
            $preferences = $guest->load('preferences')->preferences->pluck('id');

            $totalPrice = $validated['payment_details']['discountType'] == 'customize-rate' ?
                $this->customRateTotal($validated['payment_details']['customRates'])
                : $this->getTempDetailsTotalPrice($temp->details);

            $grossPrice = $this->applyVatAndServiceCharge($totalPrice);

            $tempDetailsCollection = collect($temp->details);
            $groupedData = $tempDetailsCollection->groupBy(fn($item, $key) => $item['category']['name'] . " | " . $item['date'] . " | " . $item['roomName']['bed']);

            $discountType = $validated['payment_details']['discountType'];
            $byCategoryDiscount = collect($validated['payment_details']['byCategoryDiscount']);

            $discountData = $this->calculateDiscount(
                $discountType,
                $grossPrice,
                $validated['payment_details']['byInvoiceDiscount'],
                $validated['payment_details']['byCategoryDiscount']
            );

            $roomNumberIds = $temp->details->pluck('room_number_id')->unique()->toArray();
            $roomNames = RoomNumber::whereIn('id', $roomNumberIds)->pluck('name')->toArray();
            $priceSale = $grossPrice->gross - $discountData['discount_amount'];

            $sale = $this->createSaleRoom(
                $validated,
                $guest,
                $authenticatedUser,
                $temp,
                $grossPrice,
                $totalPrice,
                $priceSale,
                $discountType,
                $discountData['discount_per'],
                $discountData['discount_amount'],
                $roomNames,
                $config
            );

            if ($sale->status == 'checkedin') $this->paymentService->confirmReservationAndAssociatedLedgerEntries($sale);
            $sale->preferences()->sync($preferences);

            $this->createSaleCategories(
                $groupedData,
                $byCategoryDiscount,
                $discountType,
                $discountData['discount_per'],
                $sale->id,
                $tempDetailsCollection,
                $validated['payment_details']['customRates']
            );

//            return response()->json($data);

            $this->createSaleRoomAllocations(
                $temp->details,
                $sale->id,
            );

            $temp->details()->delete();
            $temp->delete();
            DB::commit();

            return response()->json(['msg' => 'Room sale recorded successfully!', 'sale_id' => $sale->id, 'code' => $sale->code, 'token' => $sale->token]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function updateSaleRoom(Request $request, $saleId, PaymentService $paymentService)
    {
        try {
            DB::beginTransaction();
            $authenticatedUser = auth()->user();
            if (!$authenticatedUser) throw new UnauthorizedHttpException('', 'Unauthenticated');

            $validated = $request->validate($this->saleRoomRules());

            // Find and validate the existing sale
            $sale = SaleRoom::findOrFail($saleId);

            // Load related temp data with category
            $temp = RoomTemp::with(['details' => function ($tempDetailsQuery) {
                $tempDetailsQuery->with([
                    'category',
                    'roomName' => function ($roomNameQuery) {
                        $roomNameQuery->select('id', 'name', 'bed', 'rate', 'rate_xb');
                    }
                ]);
            }])->findOrFail($validated['temp_id']);

            // Get basic config (VAT, service charge, etc.)
            $config = Config::pluck('value', 'slug')->toArray();

            // Guest & preferences
            $guest = Guest::findOrFail($validated['guest_id']);
            $preferences = $guest->load('preferences')->preferences->pluck('id');

            // Total price before any discounts
            $totalPrice = $this->getTempDetailsTotalPrice($temp->details);

            // Calculate final prices
            $grossPrice = $this->applyVatAndServiceCharge($totalPrice);

            // Group temp details by category and then by date
            $tempDetailsCollection = collect($temp->details);
            $groupedData = $tempDetailsCollection->groupBy(fn($item, $key) => $item['category']['name'] . " | " . $item['date'] . " | " . $item['roomName']['bed']);

            $discountType = $validated['payment_details']['discountType'];
            $byCategoryDiscount = collect($validated['payment_details']['byCategoryDiscount']);

            $discountData = $this->calculateDiscount(
                $discountType,
                $grossPrice,
                $validated['payment_details']['byInvoiceDiscount'],
                $validated['payment_details']['byCategoryDiscount']
            );

            $roomNumberIds = $temp->details->pluck('room_number_id')->unique()->toArray();
            $roomNames = RoomNumber::whereIn('id', $roomNumberIds)->pluck('name')->toArray();

            $priceSale = $grossPrice->gross - $discountData['discount_amount'];

            //DELETE existing allocations and categories
            $sale->room_categories()->delete();
            $sale->sale_room_allocation()->delete();

            $amount = 0;
            $amountStatus = 'no_change';

            if ($priceSale > $sale->price_sale) {
                $amount = $priceSale - $sale->price_sale;
                $amountStatus = 'increased';
            } elseif ($priceSale < $sale->price_sale) {
                $amount = $sale->price_sale - $priceSale;
                $amountStatus = 'decreased';
            }

            if ($amountStatus !== 'no_change' && $sale->is_confirmed) $paymentService->adjustInitialLedges(
                saleId: $saleId,
                amountStatus: $amountStatus,
                amount: $amount
            );

//            if ($sale->price_sale != $priceSale) {
//                $voucher = AccVoucher::where('sale_room_id', $sale->id)->with('ledgers')->first();
//                if ($voucher) {
//                    AccLedger::where('voucher_id', $voucher->id)
//                        ->update([
//                            'debit'  => DB::raw("CASE WHEN debit > 0 THEN {$priceSale} ELSE debit END"),
//                            'credit' => DB::raw("CASE WHEN credit > 0 THEN {$priceSale} ELSE credit END"),
//                        ]);
//                }
//            }

            //UPDATE SaleRoom
            $sale->update([
                'guest_id' => $guest->id,
                'guest_name_title' => $guest->name_address,
                'guest_name_first' => $guest->first_name,
                'guest_name_last' => $guest->last_name,
                'guest_mobile' => $guest->mobile,
                'guest_mobile_emergency' => $guest->emergency_contact,
                'guest_email' => $guest->email,
                'guest_company' => $guest->company,
                'guest_address' => $guest->perm_address,
                'ref_id' => $guest->ref_id,
                'user_id' => $authenticatedUser->id,
                'master_room' => $validated['payment_details']['masterRoom'],
                'rooms' => implode(', ', $roomNames),
                'guest_qty' => $validated['payment_details']['paxGuest'],
                'room_qty' => $temp->details->sum('qty'),
                'xb_qty' => $temp->details->where('has_xb', 1)->sum('qty'),
                'date' => now(),
                'price' => $totalPrice,
                'vat_per' => $config['vat'],
                'vat_amount' => $this->fixDecimal($grossPrice->vat_amount, 2),
                'servicecharge_per' => $config['service-charge'],
                'servicecharge_amount' => round($grossPrice->service_charge_amount, 2),
                'price_gross' => $grossPrice->gross,
                'discount_type' => $discountType === 'by-invoice' ? 'invoice' : ($discountType === 'by-category' ? 'category' : 'none'),
                'discount_per' => $discountData['discount_per'],
                'discount_amount' => $this->fixDecimal($discountData['discount_amount'], 2),
                'price_sale' => $priceSale,
                'price_net' => $priceSale,
                'remarks' => $validated['payment_details']['remarks'],
                'date_checkin' => $temp->details->min('date'),
                'date_checkout' => $temp->details->max('date'),
                'eta' => Carbon::createFromFormat('h:i A', $validated['payment_details']['eta'])->format('H:i:s'),
                'etd' => Carbon::createFromFormat('h:i A', $validated['payment_details']['etd'])->format('H:i:s'),
                'date_to_confirm' => now(),
                'status' => $sale->status,
                'is_room_allocated' => 0,
            ]);

            $sale->preferences()->sync($preferences);
            // Recreate categories and allocations
            $this->createSaleCategories(
                $groupedData,
                $byCategoryDiscount,
                $discountType,
                $discountData['discount_per'],
                $sale->id,
                $tempDetailsCollection
            );

            $this->createSaleRoomAllocations(
                $temp->details,
                $sale->id
            );

            $temp->details()->delete();
            $temp->delete();
            DB::commit();
            return response()->json(['msg' => 'Room sale recorded successfully!', 'sale_id' => $sale->id, 'code' => $sale->code, 'token' => $sale->token]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function saleRoomRecordByCode(Request $request)
    {
        try {
            DB::beginTransaction();
            $sale = SaleRoom::where('code', $request->code)
                ->select('id', 'user_id', 'guest_id')
                ->with('sale_room_allocation', 'sale_guest')
                ->firstOrFail();

            $temp = RoomTemp::where('sale_id', $sale->id)->first();

            if ($temp) {
                if ($temp->updated_at < Carbon::now()->subHour(1)) {
                    $temp->delete();
                    $temp->details()->delete();
                    $temp = false;
                }
            }

            if (!$temp) {
                $temp = RoomTemp::create([
                    'sale_id' => $sale->id,
                    'user_id' => $sale->user_id,
                    'guest_id' => $sale->guest_id,
                    'type' => 'sales',
                ]);
                $details = $sale->sale_room_allocation->map(function ($allocation) {
                    return [
                        'room_number_id' => $allocation->room_number_id,
                        'room_category_id' => $allocation->room_category_id,
                        'room_name_id' => $allocation->room_name_id,
                        'bed'   => $allocation->bed,
                        'date' => $allocation->date,
                        'has_xb' => $allocation->is_xb,
                    ];
                })->toArray();
                $temp->details()->createMany($details);
            }

            $dates = collect($sale->sale_room_allocation)->pluck('date')->sort()->values();

            DB::commit();
            return response()->json([
                'sale_id' => $sale->id,
                'temp_id' => $temp->id,
                'date_from' => $dates->first(),
                'date_to' => $dates->last(),
                'guest' => $sale->sale_guest
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage());
        }
    }

    public function saleRoomRecordById(SaleRoom $saleRoom)
    {
        return response()->json($saleRoom->load([
            'sale_room_allocation' => function ($saleRoomAllocationQuery) {
                $saleRoomAllocationQuery->with([
                    'room_name' =>  function ($roomNameQuery) {
                        $roomNameQuery->select('id', 'name', 'bed');
                    },
                    'room' => function ($roomNumberQuery) {
                        $roomNumberQuery->select('id', 'name');
                    }
                ]);
            },
            'room_categories'  => function ($roomCategoriesQuery) {
                $roomCategoriesQuery->select('sale_id', 'category_id', 'gross_rate', 'is_xb', 'discount_per', 'discount_amount', 'bed', DB::raw('SUM(qty) as total_qty'))
                    ->with('category', function ($categoryQuery) {
                        $categoryQuery->select('id', 'name');
                    })
                    ->groupBy('category_id', 'is_xb')
                    ->orderBy('category_id', 'asc');
            }
        ]));
    }

    public function getSaleRecordForSuccessMessage(Request $request)
    {
        $saleRoom = SaleRoom::where('code', $request->get('code'))
            ->firstOrFail();
        return response()->json($saleRoom);
    }

    public function updateToCheckIn(SaleRoom $saleRoom, PaymentService $paymentService)
    {
        try {
            DB::beginTransaction();
            $saleRoom->status = 'checkedin';
            $saleRoom->save();
            if (!$saleRoom->is_confirmed) $paymentService->confirmReservationAndAssociatedLedgerEntries($saleRoom);
            DB::commit();
            return response()->json(['message' => 'SaleRoom status updated to pending.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update SaleRoom. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateToCheckOut(SaleRoom $saleRoom)
    {
        try {
            DB::beginTransaction();
            $saleRoom->status = 'checkout';
            $saleRoom->save();
            DB::commit();
            return response()->json(['message' => 'SaleRoom status updated to pending.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update SaleRoom. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function reservationList(Request $request)
    {
        $query = SaleRoom::with('note');
        if($request->filled('status') && $request->status != 'all'){
            if ($request->status == 'checkin-missed') {
                $query->whereIn('status', ['tentative', 'guaranteed'])
                    ->where('date_checkin', '<=', \Illuminate\Support\Carbon::yesterday()->toDateString());
            } elseif ($request->status == 'to-checkin') {
                $query->whereIn('status', ['tentative', 'guaranteed'])
                    ->where('date_checkin', '>=', \Illuminate\Support\Carbon::today()->toDateString());
            }elseif ($request->status == 'to-checkout') {
                $query->whereIn('status', ['checkedin'])
                    ->where('date_checkout', '<', \Illuminate\Support\Carbon::today()->toDateString());
            }elseif ($request->status == 'checkout-missed') {
                $query->whereIn('status', ['checkedin'])
                    ->where('date_checkout', '<=', \Illuminate\Support\Carbon::yesterday()->toDateString());
            }else{
                $query->where('status', $request->status);
            }
        }

        $query->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('guest_name_first', 'like', "%{$search}%")
                    ->orWhere('guest_mobile', 'like', "%{$search}%")
                    ->orWhere('guest_name_last', 'like', "%{$search}%");
//                    ->orWhereHas('sale_guest', function ($guestQuery) use ($search) {
//                        $guestQuery->where('first_name', 'like', "%{$search}%")
//                            ->orWhere('last_name', 'like', "%{$search}%");
//                    });
            });
        }

        $reservations = $query->paginate($request->per_page);

        return response()->json($reservations);
    }

    public function getSaleRecordForPayment(Request $request)
    {
        return response()->json(
            SaleRoom::where('code', $request->code)->firstOrFail()
        );
    }

    public function getVoucherList(Request $request)
    {
        return response()->json(
            AccVoucher::where('sale_room_id', $request->get('sale_id'))
                ->where('payment_method', '!=', 'N/A')
                ->orderBy('created_at', 'ASC')
                ->paginate($request->get('per_page'))
        );
    }
    public function getMoneyReceipts(Request $request)
    {
        $query = AccVoucher::where('sale_room_id', $request->get('sale_id'))->where('payment_method', '!=', 'N/A')->orderBy('receipt_ref', 'DESC')->get();
        return $this->withSuccess($query);
    }

    public function getLedgerListBySaleId(Request $request)
    {
        $query = AccLedger::with([
            'head',
            'bank',
            'party',
            'ledgerNote',
            'voucher.head',
            'voucher.bank',
            'voucher.party',
        ])
            ->where('sale_room_id', $request->get('sale_id'))
            ->where('type', 'sale')
            ->get();

        return response()->json(
            $query
        );
    }

    public function editSale(SaleRoom $saleRoom)
    {
        try {
            DB::beginTransaction();
            $authenticatedUser = auth()->user();
            $temp = RoomTemp::where('sale_id', $saleRoom->id)->first();

            if ($temp) {
                if ($temp->updated_at < Carbon::now()->subHour(1)) {
                    $temp->details()->delete();
                    $temp->delete();
                    $temp = false;
                } else {
                    $temp->user_id = $authenticatedUser->id;
                    $temp->touch();
                }
            }

            if (!$temp) {
                $temp = RoomTemp::create([
                    'sale_id' => $saleRoom->id,
                    'user_id' => $authenticatedUser->id,
                    'guest_id' => $saleRoom->guest_id,
                    'type' => 'sales',
                ]);
                $allocationsGrouped = $saleRoom->sale_room_allocation->groupBy(function ($allocation) {
                    return $allocation->room_number_id . '_' . $allocation->date;
                });
                $details = $allocationsGrouped->map(function ($group) {
                    $first = $group->first();
                    $hasXb = $group->contains('is_xb', 1) ? 1 : 0;
                    return [
                        'room_number_id' => $first->room_number_id,
                        'room_category_id' => $first->room_category_id,
                        'room_name_id' => $first->room_name_id,
                        'bed'   => $first->bed,
                        'date' => $first->date,
                        'has_xb' => $hasXb,
                    ];
                })->values()->toArray();
                $temp->details()->createMany($details);
            }

            $dates = collect($saleRoom->sale_room_allocation)->pluck('date')->sort()->values();
            DB::commit();
            return response()->json([
                'sale_id' => $saleRoom->id,
                'temp_id' => $temp->id,
                'date_from' => $dates->first(),
                'date_to' => $dates->last(),
                'guest' => $saleRoom->sale_guest
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage());
        }
    }

    public function discardChanges($sale_id)
    {
        DB::beginTransaction();
        try {
            // Fetch the RoomTemp record
            $roomTemp = RoomTemp::where('sale_id', $sale_id)->firstOrFail();
            $roomTemp->details()->delete();

            $roomAllocations = SaleRoomAllocation::where('room_sale_id', $sale_id)->get();
            $roomAllocationsGrouped = $roomAllocations->groupBy(function ($allocation) {
                return $allocation->room_number_id . '_' . $allocation->date;
            });
            $details = $roomAllocationsGrouped->map(function ($group) {
                $first = $group->first();
                $hasXb = $group->contains('is_xb', 1) ? 1 : 0;
                return [
                    'room_number_id' => $first->room_number_id,
                    'room_category_id' => $first->room_category_id,
                    'room_name_id' => $first->room_name_id,
                    'bed' => $first->bed,
                    'date' => $first->date,
                    'has_xb' => $hasXb,
                ];
            })->values()->toArray();

            // Create new details
            $roomTemp->details()->createMany($details);

            DB::commit();
            return response()->json("discarded");
        } catch (\Exception $e) {
            DB::rollBack();
            return  response()->json($e->getMessage());
        }
    }
}
