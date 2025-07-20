<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use App\Models\Guest;
use App\Models\Preference;
use App\Models\RoomTemp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GuestController extends Controller
{
    public function _store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'nullable|exists:room_temps,id',
            'name_address' => 'nullable|max:20',
            'last_name' => 'required|max:30',
            'first_name' => 'nullable|max:30',
            'email' => 'nullable',
            'mobile' => 'required|max:11',
            'secondary_mobile' => 'required|max:50',
            'emergency_contact' => 'required|max:50',
            'dob' => 'nullable',
            'dom' => 'nullable',
            'pres_country_id' => 'required',
            'pres_city' => 'required|max:50',
            'pres_address' => 'required',
            'perm_country_id' => 'required',
            'perm_city' => 'required|max:50',
            'perm_address' => 'required',
            'source' => 'nullable|max:100',
            'profession' => 'nullable|max:100',
            'market' => 'nullable|max:100',
            'company' => 'nullable|max:100',
            'nid' => 'nullable|max:50',
            'passport' => 'nullable|max:50',
            'passport_date' => 'nullable|max:100',
            'nationality_id' => 'nullable',
            'preference_id' => 'nullable|array|max:30',
            'reference_id' => 'nullable',
            'new_preference' => 'nullable',
        ]);

        $gender = null;
        if (!empty($validated['name_address'])) {
            if (in_array($validated['name_address'], ['Mr.', 'Mstr.'])) {
                $gender = 'Male';
            } elseif (in_array($validated['name_address'], ['Mrs.', 'Miss.'])) {
                $gender = 'Female';
            }
        }

        $guest = Guest::updateOrCreate(
            [ 'mobile' => $validated['mobile'] ],
            [
                'name_address' => $validated['name_address'] ?? null,
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'secondary_mobile' => $validated['secondary_mobile'],
                'emergency_contact' => $validated['emergency_contact'],
                'gender' => $gender,
                'dob' => $validated['dob'] ?? null,
                'dom' => $validated['dom'] ?? null,
                'pres_country_id' => $validated['pres_country_id'],
                'pres_city' => $validated['pres_city'],
                'pres_address' => $validated['pres_address'],
                'perm_country_id' => $validated['perm_country_id'],
                'perm_city' => $validated['perm_city'],
                'perm_address' => $validated['perm_address'],
                'source' => $validated['source'] ?? null,
                'profession' => $validated['profession'] ?? null,
                'market' => $validated['market'] ?? null,
                'company' => $validated['company'] ?? null,
                'nid' => $validated['nid'] ?? null,
                'passport' => $validated['passport'] ?? null,
                'passport_date' => $validated['passport_date'] ?? null,
                'nationality_id' => $validated['nationality_id'] ?? null,
                'preference_id' => json_encode($validated['preference_id'] ?? []),
                'reference_id' => $validated['reference_id'] ?? null,
            ]
        );

        $guest->preference_id = json_decode($guest->preference_id);

        if($validated['sale_id']){
            $room_temp = RoomTemp::find($validated['sale_id']);
            $room_temp->guest_id = $guest->id;
            $room_temp->save();
        }

        if(!$request->filled('preference_id') && !empty($request->new_preference)){
            Preference::create([
                'name' => $validated['new_preference'],
            ]);
        }

        return response()->json([
            'success' => true,
            'guest' => $guest,
            'message' => 'Guest created successfully',
        ]);
    }

    public function _searchByMobile(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string',
            'sale_id' => 'required|integer|exists:room_temps,id',
        ]);

        if ($request->filled('mobile') && $request->mobile !== 'null') {

            $guest = Guest::where('mobile', $request->mobile)->first();
            $room_temp = RoomTemp::find($request->sale_id);

            if ($guest) {
                $guest->preference_id = json_decode($guest->preference_id);

                $room_temp->guest_id = $guest->id;
                $room_temp->save();
            } else {
                $room_temp->guest_id = 0;
                $room_temp->save();
            }

            return response()->json([
                'success' => true,
                'guest' => $guest,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'mobile number required',
        ]);
    }

    public function store(Request $request)
    {
//        return response()->json($request->all());
        $validated = $request->validate([
            'guest_id'           => 'nullable|exists:guests,id',
            'temp_id'            => 'required|numeric',
            'name_address'       => 'nullable|string|max:20',
            'last_name'          => 'required|string|max:30',
            'first_name'         => 'nullable|string|max:30',
            'email'              => 'nullable|email|max:191',
            'mobile'             => 'required|string|max:15',
            'secondary_mobile'   => 'required|string|max:15',
            'emergency_contact'  => 'nullable|string|max:50',
            'dob'                => 'nullable|date',
            'dom'                => 'nullable|date',

            'pres_country_id'    => 'required|exists:countries,id',
            'pres_city'          => 'required|string|max:50',
            'pres_address'       => 'required|string|max:191',

            'perm_country_id'    => 'required|exists:countries,id',
            'perm_city'          => 'required|string|max:50',
            'perm_address'       => 'required|string|max:191',

            'source'             => 'nullable|string|max:100',
            'profession'         => 'nullable|string|max:100',
            'market'             => 'nullable|string|max:100',
            'company'            => 'nullable|string|max:100',

            'nid'                => 'nullable|string|max:50',
            'passport'           => 'nullable|string|max:50',
            'passport_date'      => 'nullable|string|max:100',
            'passport_exp_date'  => 'nullable|date',

            'nationality_id'     => 'nullable|exists:countries,id',
            'preference_ids'     => 'nullable|array',
            'preference_ids.*'   => 'required|numeric',
            'ref_id'       =>    'nullable|exists:users,id',
        ]);
//        return response()->json($validated);
        $validated['gender'] = $validated['gender'] ?? (
            ['mr.' => 'Male', 'mrs.' => 'Female', 'miss' => 'Female'][strtolower($validated['name_address'] ?? '')] ?? null
        );
        try {
            DB::beginTransaction();

            $guestData = collect($validated)->except(['preference_ids', 'guest_id'])->toArray();

            $guest = null;

            if (!empty($validated['guest_id'])) {
                $guest = Guest::find($validated['guest_id']);
            } elseif (!empty($validated['mobile'])) {
                $guest = Guest::where('mobile', $validated['mobile'])->first();
            }

            if ($guest) {
                $guest->update($guestData);
            } else {
                $guest = Guest::create($guestData);
            }

            if (!empty($validated['preference_ids'])) {
                $guest->preferences()->sync($validated['preference_ids']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Guest created successfully.',
                'guest'    => $guest->load('preferences'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error creating guest: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while creating the guest.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $mobile = $request->get('mobile');

//        return response()->json($mobile);

        $guests = Guest::query()
            ->where('mobile', 'like', "{$mobile}%")
            ->orWhere('secondary_mobile', 'like', "{$mobile}%")
            ->orWhere('emergency_contact', 'like', "{$mobile}%")
            ->limit(10)
            ->get();

        return response()->json($guests);

    }
}
