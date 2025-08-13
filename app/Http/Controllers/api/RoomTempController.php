<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RoomTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomTempController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RoomTemp $roomTemp)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RoomTemp $roomTemp)
    {
        DB::beginTransaction();
        try {
            if (count($roomTemp->details)) {
                $roomTemp->details()->delete();
            }

            // Delete the RoomTemp record
            $roomTemp->delete();

            DB::commit();

            return response()->json([
                'message' => 'RoomTemp and its details deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message'   => 'Failed to delete RoomTemp.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    /**
     *  extend the room temp time by updating the updated_at date field
     */

    public function extend(RoomTemp $roomTemp)
    {
        $roomTemp->touch();
        return response()->json(['message' => 'RoomTemp time extended successfully.',], 200);
    }


    /**
     * get current users tempt list
     */

    public function getCurrentUsersTempList(Request $request) {
        $authenticatedUser = auth()->user();
        $tempList = RoomTemp::where('user_id', $authenticatedUser->id)->get();
        return response()->json($tempList);
    }
}

