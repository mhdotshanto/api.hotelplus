<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RoomTempDetail;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class RoomTempDetailController extends Controller
{
    public function getTemptedRoomList(Request $request)
    {
        $authenticatedUser = auth()->user();
        if (!$authenticatedUser) throw new UnauthorizedHttpException('', 'Unauthenticated');

        $temp_id = $request->get("temp_id");
        if (!$temp_id) abort(400, 'temp_id is required');

        $temptedRooms = RoomTempDetail::where('room_temp_id', $temp_id)->with('room_xb', 'room', 'roomName')->get();
        return response()->json($temptedRooms);
    }
}
