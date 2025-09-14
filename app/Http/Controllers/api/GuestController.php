<?php

namespace App\Http\Controllers\api;

use App\Models\SaleRoom;
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

        $guests = Guest::query()
            ->where('mobile', 'like', "{$mobile}%")
            ->orWhere('secondary_mobile', 'like', "{$mobile}%")
            ->orWhere('emergency_contact', 'like', "{$mobile}%")
            ->limit(10)
            ->get();

        return response()->json($guests);
    }   

    public function guestBySale(SaleRoom $saleRoom)
    {
        return response()->json($saleRoom->sale_guest);
    }
}
